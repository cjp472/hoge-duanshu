<?php
/**
 * 同步赞赏订单到订单中心
 */

namespace App\Listeners;

use App\Events\AdmireOrderEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\Shop;


class AdmireOrderToOrderCenter
{

    /**
     * @param AdmireOrderEvent $event
     */
    public function handle(AdmireOrderEvent $event)
    {

        $param = $this->setOrderParam($event->order);
        $this->syncToOrderCenter($param,$event);

    }

    private function syncToOrderCenter($param,$event)
    {
        $appId = config('define.order_center.app_id');
        $appSecret = config('define.order_center.app_secret');
        $timesTamp = time();
        $client = hg_verify_signature($param,$timesTamp,$appId,$appSecret,$event->order->shop_id);
        try{
            $res = $client->request('POST',config('define.order_center.api.order_create'));
            $return = $res->getBody()->getContents();
            event(new CurlLogsEvent($return,$client,config('define.order_center.api.order_create')));
            if($res->getStatusCode() !== 200){
                return response([
                    'error'     => 'error-sync-order',
                    'message'   => trans('validation.error-sync-order'),
                ]);
            }
            $event->order->center_order_no = isset(json_decode($return)->result->order_no) ? json_decode($return)->result->order_no : '';
            $event->order->save();
            return $res;
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
        }
    }

    private function setOrderParam($order)
    {
        $param = [
            'outer_order_no'    => $order->order_id,
            'platform'          => PLATFORM,
            'order_type'        => 'virtual_auto_off',
            //entity-实物商品,virtual_auto_off-虚拟商品自动核销,virtual_noauto_off-虚拟商品非自动核销
            'order_total'       => round($order->price * 100), //单位(分)
            'origin_total'       => round($order->price * 100), //单位(分)
            'pay_channel'       => 'wechat', //支付方式
            //none-没有设置支付方式,wecha-微信支付,other_pay-他人代付,deposit_card-储蓄卡支付,credit_card-信用卡支付
            'buyer_message'     => '', //卖家留言
            'terminal_slug'     => 'mobile'
        ];
        $entity = $this->setEntity($param['order_type']);
        $param = array_merge($param,$entity);
        $param['extra_data'] = $this->setExtraData($order);
        $param['buyer'] = $this->setBuyer($order);
        $param['seller'] = $this->setSeller($order);
        $param['orderitems'][] = $this->setOrderItems($order);
        return $param;
    }

    private function setExtraData($order)
    {
        return [
            'avatar'    => $order->avatar,
        ];
    }

    private function setBuyer($order)
    {
        return [
            'uid'       => $order->user_id,
            'platform'  => PLATFORM,
            'username'  => $order->user_id,
            'nickname'  => (ctype_space($order->nickname) || !$order->nickname) ? DEFAULT_NICK_NAME : $order->nickname,
        ];
    }

    private function setSeller($order)
    {
        return [
            'uid'       => $order->shop_id,
            'platform'  => PLATFORM,
            'username'  => $order->shop_id,
            'nickname'  => Shop::where(['hashid'=>$order->shop_id])->value('title'),
        ];
    }

    private function setOrderItems($order)
    {
        return [
            'unit_price'    => round($order->price * 100),
            'promotion_price'   => round($order->price * 100),
            'quantity'      => $order->number?:1,
            'product_id'    => $order->content_id,
            'product_name'  => $order->title?:'直播赞赏',
            'product_img'   => $order->indexpic?:'',
            'product_type'  => 'virtual_auto_off',
            'sku'           => [
                'type'  => $order->content_type,
            ],
        ];
    }

    private function setEntity($order_type)
    {
        if($order_type == 'entity'){
            return [
                'freight'           => 0, //运费
                'receipt_username'  => '',//收货人姓名
                'receipt_contact'   => '',//收货人联系方式
                'receipt_address'   => '',//收货人地址
                'activity_id'       => '',//第三方对应的活动 id
                'activity_name'     => '',//第三方对应的活动名称
            ];
        }
        return [];
    }
}