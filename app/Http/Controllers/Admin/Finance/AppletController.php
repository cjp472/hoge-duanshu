<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2017/11/27
 * Time: 上午10:58
 */

namespace App\Http\Controllers\Admin\Finance;


use App\Events\CreateCardRecord;
use App\Events\JoinCommunityEvent;
use App\Events\OrderStatusEvent;
use App\Events\PayEvent;
use App\Events\PintuanGroupEvent;
use App\Events\PintuanRefundsPassEvent;
use App\Events\SalesTotalEvent;
use App\Events\SubscribeEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Models\AppletUpgrade;
use App\Models\Code;
use App\Models\FightGroupActivity;
use App\Models\FightGroupMember;
use App\Models\Member;
use App\Models\OrderMarketingActivity;
use App\Models\RefundOrder;
use EasyWeChat\Support\XML;
use Illuminate\Http\Request;
use App\Models\InviteCode;
use App\Models\Order;
use App\Models\Payment;
use EasyWeChat\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AppletController extends BaseController{


    public function appletOrderCallback(Request $request)
    {
        $options = config('wechat');
        $data = $request->param;
        $applet = AppletUpgrade::where('shop_id', json_decode($data['attach'])->shop_id)->first();
        $applet && $options['app_id'] = $applet->appid;
        $applet && $options['payment']['merchant_id'] = $applet->mchid;
        $applet && $options['payment']['key'] = $applet->api_key;
        $app = new Application($options);
        try{
            $response = $app->payment->handleNotify(function ($notify, $successful) use ($data) {
                //更新订单信息
                $order = Order::where('center_order_no', $notify->out_trade_no)->first();
                if (!$order) {
                    return trans('validation.no-order');
                }
                $order->pay_id = $notify->transaction_id ?: '';
                $order->pay_status = $notify->result_code == 'SUCCESS' ? 1 : 0;
                $order->pay_time = $notify->time_end ?strtotime($notify->time_end): time();
                $order->saveOrFail();

                if ($successful) {    //订单支付成功才进行下列操作
                    if (!Payment::where('order_id', $order->order_id)->value('id') && !InviteCode::where('order_id', $order->order_id)->value('id')) {//判断回调是否处理过
                        //更新开通记录,如果是购买,直接新增一条开通记录
//                        if ($order->order_type != 2) {
//                            $pay = new Payment();
//                            $userSetting = $this->buySetting($order);
//                            $paySetting = $this->paySettings($order);
//                            $pay->setRawAttributes(array_merge($userSetting, $paySetting));
//                            $pay->source = 'applet';
//                            $pay->saveOrFail();
//                            switch ($order->content_type) {
//                                case 'column':
//                                case 'course':
//                                    Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id . ':' . $order->content_type, $order->order_id);
//                                    break;
//                                case 'member_card':
//                                    event(new CreateCardRecord($order));
//                                    Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id . ':' . $order->content_type, $order->order_id);
//                                    break;
//                                case 'community':
//                                    event(new JoinCommunityEvent($order));
//                                    Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id . ':' . $order->content_type, $order->order_id);
//                                    break;
//                                default :
//                                    Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id, $order->order_id);
//                                    break;
//                            }
//                        } elseif ($order->order_type == 2) {
//                            //生成赠送邀请码
//                            $this->generateInviteCode($order);
//                        }

                        switch ($order->order_type){
                            case 2:
                                //生成赠送邀请码
                                $this->generateInviteCode($order);
                                event(new PayEvent($order));
                                event(new SalesTotalEvent($order));
                                break;
                            case 3:
                                //拼团订单，状态置为待确认
                                $order->pay_status = -6;
                                $order->save();

                                $fight_activity_id = OrderMarketingActivity::where(['order_no'=>$order->center_order_no,'marketing_activity_type'=>'fight_group'])->value('marketing_activity_id');
                                $fight_activity = FightGroupActivity::find($fight_activity_id);                                $fight_activity->setKeyType('string');
                                $member_group = Cache::get('fight:group:member:'.$order->center_order_no);
                                $member_group = json_decode($member_group);
                                $member_id = Member::where(['uid'=>$order->user_id])->value('id');
                                //检测是否已经加入当前拼团组
                                $is_group_member = FightGroupMember::where([
                                    'fight_group_id'=> $member_group ? $member_group->group_id : '',
                                    'member_id'     => $member_id,
                                    'is_del'        => 0,
                                    'join_success'  => 1,
                                    'has_paid'        => 0,
                                ])->first();
                                if(!$is_group_member) {
                                    $group = [
                                        'member' => $order->user_id,
                                        'order_no' => $order->order_id,
                                        'fight_group_activity' => $fight_activity->id,
                                    ];
                                    if ($member_group && $member_group->group_id) {
//                                        $end_time = $fight_activity->end_time ? strtotime($fight_activity->end_time) : 0;
//                                        $key = 'pintuan:group:member:num:' . $member_group->group_id;
//                                        Cache::increment($key);
//                                        //设置缓存过期时间
//                                        Redis::expire(config('cache.prefix') . ':' . $key, $end_time - time() > 0 ? ($end_time - time()) + 3600 : 0);
                                        $group['fight_group'] = $member_group->group_id;
                                    }
                                    //通知python创建拼团组
                                    event(new PintuanGroupEvent($group,$order));
                                }
                                //删除缓存的拼团组和会员关联，释放资源
                                Cache::forget('fight:group:member:'.$order->center_order_no);
                                break;
                            default:
                                $pay = new Payment();
                                $userSetting = $this->buySetting($order);
                                $paySetting = $this->paySettings($order);
                                $pay->setRawAttributes(array_merge($userSetting, $paySetting));
                                $pay->source = 'applet';
                                $pay->saveOrFail();
                                switch ($order->content_type) {
                                    case 'column':
                                    case 'course':
//                                        Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id . ':' . $order->content_type, $order->order_id);
                                        break;
                                    case 'member_card':
                                        event(new CreateCardRecord($order));
//                                        Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id . ':' . $order->content_type, $order->order_id);
                                        break;
                                    case 'community':
                                        $params = [
                                            'shop_id' => $order->shop_id,
                                            'community_id' => $order->content_id,
                                            'member_id' => $order->user_id,
                                            'member_name' => $order->nickname,
                                            'source' => 'purchase',
                                        ];
                                        event(new JoinCommunityEvent($params));
//                                        Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id . ':' . $order->content_type, $order->order_id);
                                        break;
                                    default :
//                                        Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id, $order->order_id);
                                        break;
                                }
                                event(new SubscribeEvent($order->content_id, $order->content_type, $order->shop_id, $order->user_id, $pay->payment_type));
                                event(new PayEvent($order));
                                event(new SalesTotalEvent($order));
                                break;

                        }
                        event(new OrderStatusEvent($data,$order->shop_id));
                    }
                }
                return true;
            });
        }catch (\Exception $exception){
            return [
                'return_code'  => 'FAIL',
                'return_msg'   => $exception->getMessage(),
            ];
        }
        return $response;
    }


    private function buySetting(Order $order)
    {
        return [
            'user_id'           => $order->user_id,
            'nickname'          => $order->nickname,
            'avatar'            => $order->avatar,
            'payment_type'      => 1,
        ];
    }

    private function paySettings(Order $order)
    {
        return [
            'content_id'            => $order->content_id,
            'content_type'          => $order->content_type,
            'content_title'         => $order->content_title,
            'content_indexpic'      => $order->content_indexpic,
            'order_id'              => $order->order_id,
            'order_time'            => $order->pay_time,
            'price'                 => $order->price,
            'shop_id'               => $order->shop_id,
        ];
    }

    /**
     * 生成赠送邀请码
     * @param $order
     * @return bool
     */
    private function generateInviteCode($order)
    {
        $exists = InviteCode::where(['order_id'=>$order->order_id])->first();
        if($exists || $order->pay_status != 1) {
            return true;
        }
        $inviteCode = new InviteCode();
        $inviteCode->setRawAttributes([
            'shop_id'       => $order->shop_id,
            'type'          => 'share',
            'content_id'    => $order->content_id,
            'content_type'  => $order->content_type,
            'content_title' => $order->content_title,
            'content_indexpic' => $order->content_indexpic,
            'order_id'      => $order->order_id,
            'buy_time'      => $order->pay_time,
            'user_id'       => $order->user_id,
            'user_name'     => $order->nickname,
            'avatar'        => $order->avatar,
            'price'         => $order->price,
            'total_num'     => intval($order->number),
        ]);
        
        $order->content_type == 'member_card' && $inviteCode->setExtraData($order->getExtraData());
        $inviteCode->save();
        if ($inviteCode->getKey()) {
            for($i=1;$i<=intval($order->number);$i++){
                $code[] = [
                    'shop_id' => $order->shop_id,
                    'code_id' => $inviteCode->getKey(),
                    'code'    => rand(1000, 9999) . '-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                    'status'  => 0
                ];
            }
            Code::insert($code);
        }
        return true;
    }

    /**
     * 小程序退款回调
     */
    public function appletRefundCallback(Request $request)
    {
        $options = config('wechat');
        $data = $request->input('param');
        $applet = AppletUpgrade::where('appid', $data['appid'])->first();
        $applet && $options['app_id'] = $applet->appid;
        $applet && $options['payment']['merchant_id'] = $applet->mchid;
        $applet && $options['payment']['key'] = $applet->api_key;
        $app = new Application($options);
        try {
            $response = $app->payment->handleRefundNotify(function ($notify, $successful) use ($data) {
                $req_info = $notify->req_info;
                $order = Order::where(['center_order_no'=>$req_info['out_trade_no']])->first();
                //订单存在且处于退款中状态
                if($order && $order->pay_status == -3){
                    $order->pay_status = $req_info['refund_status'] == 'SUCCESS' ? -4 : -5;
                    $order->save();
                    $refund_order = RefundOrder::where(['refund_no'=>$req_info['out_refund_no']])->first();
                    if($refund_order && $refund_order->refund_status == 1){
                        $refund_order->refund_status = $req_info['refund_status'] == 'SUCCESS' ? 2 : 3;
                        $refund_order->wechat_refund_id = $req_info['refund_id'];
                        $refund_order->extra = json_encode($notify->all());
                        $refund_order->save();
                        $req_info['refund_status'] == 'SUCCESS' && event(new PintuanRefundsPassEvent($refund_order));
                    }
                }
                return true;
            });
        } catch (\Exception $exception) {
            return [
                'return_code' => 'FAIL',
                'return_msg' => $exception->getMessage(),
            ];
        }
        return $response;
    }

}