<?php
namespace App\Jobs;

use App\Events\CurlLogsEvent;
use App\Events\SystemEvent;
use App\Models\ShopClose;
use App\Models\ShopFlow;
use App\Models\ShopScore;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Settlement
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct($shop,$time,$is_enouph = 1 )
    {
        $this->time = $time;
        $this->shop = $shop;
        $this->enouph = $is_enouph;
    }

    public function handle()
    {
        $default_storage = $default_flow = 0;
        $shop = Shop::where('hashid', $this->shop)
            ->select('version','flow','storage')
            ->first();
        if(!$shop) {
            return false;
        }
        $default_storage = $shop->storage;
        $default_flow = $shop->flow;


        //查询昨日的总扣费情况
        $_price = ShopFlow::where('time',$this->time)->where('flow_type',1)->where('shop_id',$this->shop)->sum('price');

        //计算总费用
        if($_price){
            $price = $_price;
            $order_id = time().mt_rand(111111,999999);
            $data = ['serial_number'=>$order_id,'value'=>'-'.$price,'brief'=>'代币消费'];
            $client = $this->initClient($this->shop,$data);//生成签名
            $url = config('define.service_store.api.score_manage');
            $res = $client->request('post',$url,$data);
            //请求扣费
            $r = json_decode($res->getBody()->getContents());
            event(new CurlLogsEvent(json_encode($r),$client,$url));
            if($r->error_code == 0){
                $result = $r->result;
            }else{
                $result = [];
            }
            $param = [
                'shop_id'     => $this->shop,
                'order_id'    => $order_id,
                'order_type'  => 'pay',
                'order_price' => $price,
                'order_time'  => $this->time,
                'score'       => '-'.$price,
                'order_status'=> ($result && $result->arrearage!=0)?-1:1,
            ];
            //记录扣费情况
            $sc = new ShopClose($param);
            $sc->save();

            //判断流量是否充足
            $is_flow_enouph = $this->isEnouphFlow($this->shop,$this->time,$default_flow);
            $is_storage_storage = $this->isEnouphStorage($this->shop,$this->time,$default_storage);

            //如果流量不充足且短书币小于5,发送提醒通知
            if(!$is_flow_enouph && !$is_storage_storage && $result->token < 5 && $result->token >= 0)
            {
                event(new SystemEvent($this->shop,trans('notice.title.score.not_enough'),trans('notice.content.score.not_enough'),0,-1,'系统管理员'));
            }elseif( !$is_flow_enouph && !$is_storage_storage && $result->token < 0 ) {
                //通知 短书币不足通知
                event(new SystemEvent($this->shop,trans('notice.title.score.no_money'),trans('notice.content.score.no_money'),0,-1,'系统管理员'));
            }

            //如果流量不充足且短书币<0
            if(!$is_flow_enouph && $result->token<0){
                //如果下载流量不足,则2天后关闭店铺
                $sc = new ShopClose();
                $sc->shop_id = $this->shop;
                $sc->method = 'close';
                $sc->reason = 'no_flow';
                $sc->event_time = time();
                $sc->process_time = strtotime('+2 days');
                $sc->save();
            }

            //如果流量不充足且短书币<0
            if(!$is_storage_storage && $result->token<0){

                //如果存储量不足,则30天后删除店铺素材内容
                $sc = new ShopClose();
                $sc->shop_id = $this->shop;
                $sc->method = 'delete';
                $sc->reason = 'no_storage';
                $sc->event_time = time();
                $sc->process_time = strtotime('+30 days');
                $sc->save();

                //通知 账号因存储空间欠费已被冻结
                $content = trans('notice.content.score.storage_arrears');
                event(new SystemEvent($this->shop,trans('notice.title.score.storage_arrears'),$content,0,-1,'系统管理员'));
            }
        }
    }

    private function initClient($shop_id,$data)
    {
        $appId = config('define.service_store.app_id');
        $appSecret = config('define.service_store.app_secret');
        $timesTamp = time();
        $client = hg_verify_signature($data,$timesTamp,$appId,$appSecret,$shop_id);
        return $client;
    }


    /**
     * 判断流量是否充足
     * @param $shop_id 店铺id
     * @param $time 计算时间
     * @param $default_flow 赠送流量
     * @return bool true 充足 false-不充足
     */
    private function isEnouphFlow($shop_id,$time,$default_flow)
    {
        $start_time = strtotime(date('Y-m-01 00:00:00', $time));
        $end_time = strtotime(date('Y-m-d 23:59:59', $time));

        //计算到当日的流量总消耗
        $flow = ShopFlow::where(['remark' => 'flow', 'shop_id' => $shop_id, 'flow_type' => 0])->whereBetween('time', [$start_time, $end_time])->sum('numberical');

        if( $default_flow - $flow <= 0 ) {
            return false;
        }
        
        return true;
    }

    /**
     * @param $shop_id 店铺id
     * @param $time 计算时间
     * @param $default_storage 赠送存储空间
     * @return bool true 充足 false-不充足
     */
    private function isEnouphStorage($shop_id,$time,$default_storage)
    {
        $start_time = strtotime(date('Y-m-01 00:00:00', $time));
        $end_time = strtotime(date('Y-m-d 23:59:59', $time));

        //计算到当日的流量总消耗
        $flow = ShopFlow::where(['remark' => 'storage', 'shop_id' => $shop_id, 'flow_type' => 0])->whereBetween('time', [$start_time, $end_time])->sum('numberical');

        if( $default_storage - $flow <= 0 ) {
            return false;
        }

        return true;
    }
}