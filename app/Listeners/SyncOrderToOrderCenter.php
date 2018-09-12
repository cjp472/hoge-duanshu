<?php
/**
 * 同步订单到订单中心
 */

namespace App\Listeners;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\OrderMakeEvent;
use App\Models\FightGroup;
use App\Models\CardRecord;
use App\Models\FightGroupActivity;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionRecord;
use App\Models\Shop;
use GuzzleHttp\Client;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SyncOrderToOrderCenter
{


    public function handle(OrderMakeEvent $event)
    {
        $param = $this->setOrderParam($event->order,$event->content,$event->request); // member_card的price属性值价格会在下单接口修改，因为会员卡加上了规格，会员卡字段price并不代表会员购买的价格。【需求会员卡2-0】
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
            $order_return = json_decode($return);
            if (isset($order_return->result)){
                $order_result = json_decode($return)->result;
                $event->order->center_order_no = isset($order_result->order_no) ? $order_result->order_no : '';
                $order_status_text = isset($order_result->status) ? $order_result->status : '';
                $order_update_status_time = $event->order->update_status_time;
                $center_order_update_status_time = isset($order_result->sequence_time->$order_status_text) ? $order_result->sequence_time->$order_status_text : 0;
                if ($center_order_update_status_time > $order_update_status_time && $order_status_text) {
                    $order_status = config('define.order_status_map.' . $order_status_text);
                    $event->order->pay_status = $order_status;
                    $event->order->update_status_time = $center_order_update_status_time;
                }
                $event->order->save();
                return $res;
            }
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
        }
    }

    private function setOrderParam(Order $order,$content,$request)
    {
        $type_array = [
            1   => 'generic',
            3   => 'fight_groups',
            4   => 'group_buying',
        ];
        //如果有推广员ID，判断是否清退等
        if($request->promoter_id){
            $promotionStatus = hg_check_promotion($request->promoter_id,$order->shop_id);
            if(!$promotionStatus){
                $request->offsetUnset('promoter_id');
            }
        }
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
            'terminal_slug'     => 'mobile',
            'type'              => isset($type_array[$order->order_type]) ? $type_array[$order->order_type] : 'generic',
            'distribution_settle_time'  => 'after_sale_service'
        ];
        $entity = $this->setEntity($param['order_type']);
        $param = array_merge($param,$entity);
        $param['extra_data'] = $this->setExtraData($order,$request);
        $param['buyer'] = $this->setBuyer($order);
        $param['seller'] = $this->setSeller($order);
        $param['orderitems'][] = $this->setOrderItems($order,$content);
//        $distributors = $this->setDistributors($request);
//        $param = array_merge($param,$distributors);
        return $param;
    }

    private function setExtraData($order,$request)
    {
        $extra =  [
            'avatar'    => $order->avatar,
        ];
        if($request->content_type == 'member_card') { // 购买会员卡，附加上购买的规格
            $extra['membercard_option'] = $request->membercard_option;
        }
        if($order->order_type == 3) { // 拼团购买
            if ($request->fight_group_activity_id) {
                $fight_group_activity_id = $request->fight_group_activity_id;
            } else {
                $fight_group_activity = FightGroupActivity::where(['product_identifier' => $request->content_id, 'product_category' => $request->content_type])->first(['id as fight_group_activity_id']);
                $fight_group_activity_id = $fight_group_activity->fight_group_activity_id;
            }
            if ($fight_group_activity_id) {
                //添加拼团编号
                $key = 'pintuan:second:no:' . $fight_group_activity_id . ':' . time();
                Cache::increment($key);
                Redis::expire(config('cache.prefix') . ':' . $key, 60);
                $pintuan_no = Cache::get($key);
                $extra['pintuan_no'] = 'PT' . date('ymdHis') . sprintf('%03s', $pintuan_no);
                $extra['fight_group_activity_id'] = $fight_group_activity_id;
                $extra['fight_group_id'] = $request->group_id;
            }
        }
        return $extra;
    }

    private function setBuyer(Order $order)
    {
        return [
            'uid'       => $order->user_id,
            'platform'  => PLATFORM,
            'username'  => $order->user_id,
            'nickname'  => (ctype_space($order->nickname) || !$order->nickname)? DEFAULT_NICK_NAME : $order->nickname,
        ];
    }

    private function setSeller(Order $order)
    {
        return [
            'uid'       => $order->shop_id,
            'platform'  => PLATFORM,
            'username'  => $order->shop_id,
            'nickname'  => Shop::where(['hashid'=>$order->shop_id])->value('title'),
        ];
    }

    private function setOrderItems(Order $order,$content)
    {
        $promotion_price = $this->promotion_price($content,$order);
        $promotion_price = isset($content->promotion_price) ? round($content->promotion_price * 100) : $promotion_price;
        $item_total = $order->number * $promotion_price;
        $params = [
            'unit_price'    => round($content->price * 100),
            'promotion_price'   => $promotion_price,
            'item_total'    => $item_total,      //可退款金额
            'quantity'      => $order->number,
            'product_id'    => $content->hashid,
            'product_name'  => $content->title,
            'product_img'   => $content->indexpic,
            'product_type'  => 'virtual_auto_off',
            'sku'           => [
                'type'      => $order->content_type,
                'id'        => $order->content_type,
            ],
            'product_model' => $order->content_type,
        ];
        $promotion_record = PromotionRecord::where(['shop_id' => $order->shop_id,
            'order_id' => $order->order_id])->first();
        if (($promotion_record)) {
            $params['is_distribution'] = 1;
            $params['distribution_config'][] = [
                'uid' => $promotion_record->promotion_id,
                'rate' => round($promotion_record->money_percent / 100, 2),
            ];
            if ($promotion_record->visit_id) {
                $params['distribution_config'][] = [
                    'uid' => $promotion_record->visit_id,
                    'rate' => round($promotion_record->visit_percent / 100, 2),
                ];
            }
        } else {
            $params['is_distribution'] = 0;
            $params['distribution_config'] = [];
        }
        return $params;
    }

    /*
     * 处理折扣单价，后期如果有其他营销活动，也放到这边处理
     */
    private function promotion_price($content,$order)
    {
        if($order->content_type =='member_card' || $order->order_type == 2){
            return round($content->price*100);
        }elseif ($order->order_type == 3) {//拼团订单
            $fight_group_activity = FightGroupActivity::where(['product_identifier' => request('content_id'), 'product_category' => request('content_type'),'is_del'=>0])->orderByDesc('end_time')->first(['origin_price', 'now_price']);
            $fight_price = $fight_group_activity ? round($fight_group_activity->now_price) : 0;
            return $fight_price;
        }else {
            $record = CardRecord::where(['member_id' => $order->user_id, 'shop_id' => $order->shop_id])->where('end_time', '>', time())->get()->toArray(); //获取该会员订购的所有会员卡（在有效期内的）
            $record && array_multisort(array_column($record, 'discount'), SORT_ASC, $record); //根据折扣高低排序数组
            $price = number_format(round($content->price * ($record ? $record[0]['discount'] / 10 : 1), 2), 2);  //折扣后的价格
            $price = str_replace(',', '', $price);
            $promotion_price = $price < 0 ? 0.00 : $price;
            return round($promotion_price*100);
        }
    }


    /**
     * 分销员信息
     * @param $request
     * @return array
     */
    private function setDistributors($request){
        if($request->promoter_id) {
            $visit_id = Promotion::where('promotion_id',$request->promoter_id)->value('visit_id');

            $distributors =  [
                [
                    'id' => trim($request->promoter_id),
                ]
            ];
            if($visit_id){
                $distributors[] = [
                  'id'      => $visit_id,
                ];
            }
            return [
                'distributors' => $distributors
            ];
        }
        return [];
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