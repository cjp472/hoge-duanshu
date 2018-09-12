<?php

namespace App\Listeners;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\NoticeEvent;
use App\Events\PintuanGroupEvent;
use App\Events\PintuanPaymentEvent;
use App\Models\FightGroup;
use App\Models\FightGroupActivity;
use App\Models\FightGroupFailed;
use App\Models\FightGroupMember;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PintuanGroup implements ShouldQueue
{

    use InteractsWithQueue;
    /**
     * 队列名称
     * @var string
     */
    public $queue = DEFAULT_QUEUE;

    /**
     * Handle the event.
     *
     * @param  PintuanGroupEvent  $event
     * @return void
     */
    public function handle(PintuanGroupEvent $event)
    {
        $param = $event->param;
        $order = $event->order;

        $appId = config('define.inner_config.sign.key');
        $appSecret = config('define.inner_config.sign.secret');
        $timesTamp = time();
        $client = hg_verify_signature($param,$timesTamp,$appId,$appSecret,$event->order->shop_id);
        try{
            if(isset($param['fight_group'])){
                $url = config('define.python_duanshu.api.group_join');
            }else {
                $url = config('define.python_duanshu.api.group_create');
            }
            $res = $client->request('POST',$url);
            $return = $res->getBody()->getContents();
//            $return = '{"error_code":"0","error_message":"","result":{"id":"f6b001b157674d27a9fa5231a351bd4d","create_time":1527477186,"update_time":1527495790,"is_del":false,"status":"complete","refund_success":false,"fight_group_activity":"1a19edc7c54e4eb4beb01f9c7fd0b6d1","shop":18}}';
            event(new CurlLogsEvent($return,$client,$url));
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
            $this->saveFailedFightGroupRequest($param,$event->order->shop_id);
            $response = new Response([
                'error'     => 'join-fight-group-error',
                'message'   => trans('validation.join-fight-group-error'),
            ], 200);
            throw new HttpResponseException($response);
        }
        //检测拼团组状态，成功拼团，对应的拼团组成员开通内容权限
        $fight_group = json_decode($return);
        if($fight_group && !$fight_group->error_code){
            $fight_group_id = isset($fight_group->result->id) ?  $fight_group->result->id : '';
            if($fight_group_id){
                $fight_activity = FightGroupActivity::findOrFail($fight_group->result->fight_group_activity);
                $end_time = $fight_activity->end_time ? strtotime('+8 hour',strtotime($fight_activity->end_time)) : 0;
                $key = 'pintuan:group:member:num:' . $fight_group_id;
                Cache::increment($key);
                //设置缓存过期时间
                Redis::expire(config('cache.prefix') . ':' . $key, $end_time - time() > 0 ? ($end_time - time()) + 3600 : 0);

                $fight_group_status = $fight_group->result->status;
                if($fight_group_status == 'complete'){
                    event(new PintuanPaymentEvent($fight_group_id,$order));
                    $group_member = FightGroupMember::where(['fight_group_id'=>$fight_group_id,'join_success'=>1])->pluck('redundancy_member');
                    foreach ($group_member as $item) {
                        $member = $item ? json_decode($item,1) : [];
                        event(new NoticeEvent(0,'您的拼团已成功，赶紧去看看吧',$order->shop_id,$member['uid'] ,$member['nick_name'],['title'=>'点击查看','content_id'=>$order->content_id,'type'=>$order->content_type,'fight_group_id'=>$fight_group_id,'fight_group_activity_id'=>$fight_activity->id,'out_link'=>''],'拼团消息'));
                    }
                }

                $order_param = [
                    'extra_data'    => [
                        'fight_group_id'    => $fight_group_id
                    ]
                ];
                $order_client = hg_verify_signature($order_param,time(),config('define.order_center.app_id'),config('define.order_center.app_secret'),$order->shop_id);
                try{
                    $order_url = str_replace('{order_no}', $order->center_order_no, config('define.order_center.api.m_order_update'));
                    $order_res = $order_client->request('PUT',$order_url);
                    $order_return = $order_res->getBody()->getContents();
                    event(new CurlLogsEvent($order_return,$order_client,$order_url));
                }catch (\Exception $exception){
                    event(new ErrorHandle($exception,'order_center'));
                }

                //如果是拼团失败重试的，成功之后将失败数据清除
                if(FightGroupFailed::where(['order_id'=>$param['order_no']])->first()){
                    FightGroupFailed::where(['order_id'=>$param['order_no']])->delete();
                }
            }
        }else{
            $this->saveFailedFightGroupRequest($param,$event->order->shop_id);
        }
        return true;

    }

    private function saveFailedFightGroupRequest($param,$shop_id){
        $fight_group_failed = FightGroupFailed::where('order_id',$param['order_no'])->first();
        if(!$fight_group_failed){
            $fight_group_failed = new FightGroupFailed();
            $fight_group_failed->setRawAttributes([
                'shop_id'  => $shop_id,
                'order_id'  => $param['order_no'],
                'param'     => serialize($param),
            ]);
        }else{

            $fight_group_failed->param = serialize($param);
            $fight_group_failed->try_times = $fight_group_failed->try_times + 1;
            $fight_group_failed->save();
        }
    }
}
