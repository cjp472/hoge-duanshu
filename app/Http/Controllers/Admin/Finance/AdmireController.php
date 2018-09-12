<?php
/**
 * 赞赏回调管理
 */
namespace App\Http\Controllers\Admin\Finance;

use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

use App\Events\AdmireEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Admire;
use App\Models\AdmireOrder;
use App\Models\Alive;
use App\Models\AliveMessage;
use App\Events\OrderStatusEvent;
use App\Models\AppletUpgrade;
use EasyWeChat\Foundation\Application;


class AdmireController extends BaseController
{

    public function admireCallback(Request $request)
    {
        //更新订单信息
        $order = AdmireOrder::where('center_order_no',$request->out_biz_no)->first();
        if(!$order){
            $this->error('no-order');
        }
        $order->pay_id = $request->asset_detail_no;
        $order->pay_status = $request->result;
        $order->pay_time = $request->pay_time ?: time();
        $order->saveOrFail();
        if($order->pay_status == 1) {
            $admire = Admire::where('center_order_no',$request->out_biz_no)->first();
            if(!$admire){
                $pay = new Admire();
                $pay->setRawAttributes($this->formatAdmireData($order));
                $pay->center_order_no = $request->out_biz_no;
                $pay->saveOrFail();
                $this->createAdmireMessage($order);
                event(new AdmireEvent($order));
            }
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 小程序赞赏回调
     *
     * @param Request $request
     * @return void
     */
    public function appletAdmireCallback(Request $request){
        $options = config('wechat');
        $data = $request->param;
        $applet = AppletUpgrade::where('shop_id', json_decode($data['attach'])->shop_id)->first();
        $options['app_id'] = $applet->appid;
        $options['payment']['merchant_id'] = $applet->mchid;
        $options['payment']['key'] = $applet->api_key;
        $app = new Application($options);
        $response = $app->payment->handleNotify(function ($notify, $successful) use ($data) {
            $order = AdmireOrder::where('center_order_no', $notify->out_trade_no)->first();
            
            if (!$order) {
                return trans('validation.no-order');
            }
            if($order->pay_status == 1){
                return true;
            }

            if ($successful) {
                $order->pay_id = $notify->transaction_id ?: '';
                $order->pay_status = 1;
                $order->pay_time = $notify->time_end ? strtotime($notify->time_end): time();
                $order->saveOrFail();

                $admire = new Admire();
                $admire->setRawAttributes($this->formatAdmireData($order));
                $admire->center_order_no = $order->center_order_no;
                $admire->saveOrFail();
                $this->createAdmireMessage($order);
                event(new AdmireEvent($order));
                event(new OrderStatusEvent($data, $order->shop_id));
            }
        });
        return $response;

    }

    private function createAdmireMessage($order){
        $data = [
            'content_id'=> $order->content_id,
            'shop_id'   => $order->shop_id,
            'member_id' => $order->user_id,
            'type'      => 4,
            'message'   => '赞赏了 '.$order->lecturer_name.'<span>'.($order->price).'元红包</span>',
            'time'      => time(),
            'tag'       => '',
            'nick_name' => $order->nickname,
            'avatar'    => $order->avatar?:'',
        ];
        $msg_id = AliveMessage::insertGetId($data);
        $message = AliveMessage::findOrFail($msg_id);
        $key = 'alive:message:'.$order->shop_id.':'.$order->content_id;
        $kid = $this->formatSendMsg($key,$message);
        Redis::hset('live:message:index',$order->shop_id.':'.$order->content_id.':'.$msg_id,$kid);

        $keys = 'alive:message:lecturer:'.$order->shop_id.':'.$order->content_id;
        $kids = $this->formatSendMsg($keys,$message);
        Redis::hset('live:message:index',$order->shop_id.':'.$order->content_id.':'.$msg_id.':lecturer',$kids);
    }

    private function formatSendMsg($key,$data){
        $index = Redis::rpush($key,serialize($data));
        $data->kid = $index-1;
        Redis::lset($key,$data->kid,serialize($data));
        return $data->kid;
    }


    private function formatAdmireData(AdmireOrder $order)
    {
        return [
            'shop_id'               => $order->shop_id,
            'content_id'            => $order->content_id,
            'member_id'             => $order->user_id,
            'lecturer'              => $order->lecturer,
            'admire_time'           => $order->pay_time,
            'money'                 => $order->price,
        ];
    }



}