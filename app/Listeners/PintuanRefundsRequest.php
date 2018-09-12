<?php

namespace App\Listeners;

use App\Events\NoticeEvent;
use App\Events\PintuanRefundsEvent;
use App\Events\PintuanRefundsRequestEvent;
use App\Events\SystemEvent;
use App\Models\AppletUpgrade;
use App\Models\FightGroup;
use App\Models\FightGroupMember;
use App\Models\RefundOrder;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class PintuanRefundsRequest
{

    /**
     * Handle the event.
     *
     * @param  PintuanRefundsRequestEvent  $event
     * @return void
     */
    public function handle(PintuanRefundsRequestEvent $event)
    {

        $fight_group_id = $event->fight_group_id;
        $fight_group = FightGroup::where(['id'=>$fight_group_id, 'status'=>'failed'])->first();
        $where = ['fight_group_id'=>$fight_group_id];
        if(!$fight_group){
            $where['join_success'] = 0;
        }
        $fight_group_member = FightGroupMember::where($where)->get(['order_no','fight_group_id','redundancy_member']);
        if($fight_group_member->isNotEmpty()){
            foreach ($fight_group_member as $item) {

                $member = $item->redundancy_member ? json_decode($item->redundancy_member,1): [];
                $item->member_uid = $member['uid'];
                //订单存在且订单是未确认时才进行退款操作
                if($item->order && $item->order->pay_status == -6) {
                    event(new NoticeEvent(
                        0,
                        '您参与的拼团超过有效期拼团失败，系统会自动将所支付的款项退回，具体到账时间以各银行为准。',
                        $item->order->shop_id,
                        $member['uid'],
                        $member['nick_name'],
                        ['title' => '点击查看', 'content_id' => $item->order->content_id, 'type' => $item->order->content_type, 'fight_group_id' => $fight_group_id, 'fight_group_activity_id' => FightGroup::where(['id' => $fight_group_id])->value('fight_group_activity_id') ?: '', 'out_link' => ''],
                        '拼团失败'));

                    $order_source = $item->order ? $item->order->source : 'h5';
                    if ($order_source == 'applet') {
                        $this->appletRefunds($item->order);
                    } else {
                        $param = [
                            'buyer_id' => $item->member_uid,
                            'order_no' => $item->order_no,  //字段待确认
                            'order_item' => '',
                            'quantity' => 1,
                            'refund_type' => 'money',
                            'refund_reason' => '短书拼团失败退款',
                            'auto_refund' => true
                        ];
                        event(new PintuanRefundsEvent($param));
                    }
                }
            }
        }
    }

    /**
     * 小程序退款
     */
    private function appletRefunds($order){

        $order_no = $order->center_order_no;
        $retry_status = [1,3];
        $refund_order = RefundOrder::where(['order_no'=>$order->center_order_no])->first();
        if(!$refund_order){
            $refund_order = new RefundOrder();
            $refund_no = date('ymdHis').str_pad(time()+mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

            $refunds_param = [
                'order_no'  => $order_no,
                'order_id'  => $order->order_id,
                'shop_id'  => $order->shop_id,
                'refund_no' => $refund_no,
            ];
            $refund_order->setRawAttributes($refunds_param);
            $refund_order->save();
        }elseif ($refund_order && !in_array($refund_order->status,$retry_status)){
            //订单退款中或者已经退款不再发起申请退款请求
            return false;
        }
        //先通知订单中心
        $param = [
            'buyer_id' => $order->user_id,
            'order_no' => $order->order_id,  //字段待确认
            'order_item' => '',
            'quantity' => 1,
            'refund_type' => 'money',
            'refund_reason' => '短书拼团失败退款-小程序端',
        ];
        event(new PintuanRefundsEvent($param));


        $param = [
            'order_no'      => $order_no,
            'refund_no'     => $refund_order->refund_no,
            'total_fee'     => round($order->price*100),
            'refund_fee'    => round($order->price*100),
            'mch_id'        => AppletUpgrade::where(['shop_id'=>$order->shop_id])->value('mchid')?:'',
            'shop_id'       => $order->shop_id,
        ];
        $return = hg_applet_refunds($param,'out_trade_no');
        //退款申请提交成功，
        if($return && $return['return_code'] == 'SUCCESS' && $return['result_code'] == 'SUCCESS'){
            //退款中
            $refund_order->refund_status = 1;
            $refund_order->wechat_refund_id = $return['refund_id'];
            $refund_order->save();
            //订单状态修改,退款中，
            $order->pay_status = -3;
            $order->save();
        }else{
            //退款申请失败，后期可以根据此状态进行重试
            $refund_order->refund_status = 3;
            $refund_order->save();
            //退款失败，
            $order->pay_status = -5;
            $order->save();
            //失败系统通知
            if($return && isset($return['err_code']) && in_array($return['err_code'], [ 'NOTENOUGH','USER_ACCOUNT_ABNORMAL'])){
                event(new SystemEvent(
                    $order->shop_id,
                    str_replace('{type}', '拼团', trans('validation.refund-failed-notice-title')),
                    str_replace(['{type}','{order_no}'], ['拼团',$order_no], trans('validation.'.$return['err_code'])),
                    0,
                    -1,
                    '系统管理员'
                ));
            }
        }
        return true;

    }
}
