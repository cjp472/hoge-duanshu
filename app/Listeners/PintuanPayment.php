<?php

namespace App\Listeners;

use App\Events\CreateCardRecord;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\PayEvent;
use App\Events\PintuanPaymentEvent;
use App\Events\SalesTotalEvent;
use App\Events\SubscribeEvent;
use App\Models\Column;
use App\Models\FightGroup;
use App\Models\FightGroupMember;
use App\Models\Order;
use App\Models\Payment;

use App\Models\PromotionRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PintuanPayment
{

    /**
     * Handle the event.
     *
     * @param  PintuanPaymentEvent  $event
     * @return void
     */
    public function handle(PintuanPaymentEvent $event)
    {
        $fight_group_id = $event->fight_group_id;
        $order = $event->order;
        $fight_group = FightGroup::find($fight_group_id);
        //拼团成功
        if($fight_group->status == 'complete'){

            $member_order = FightGroupMember::where(['fight_group_id'=>$fight_group_id,'is_del'=>0,'join_success'=>1])
                ->leftJoin('order','order.order_id','=','fightgroupmember.order_no')
                ->select(['order.shop_id','order.user_id','order.center_order_no','order.nickname', 'order.number',
                    'order.content_id','order.content_type', 'order.avatar','order.price','order.order_id',
                    'order.pay_time','order.source', 'fightgroupmember.fight_group_id'])
                ->get();

            //订单状态置为完成

            Order::whereIn('order_id',$member_order->pluck('order_id')->toArray())->update(['pay_status'=>1]);

            $payment = [];
            if($member_order->isNotEmpty()){

                $content_id = $order->content_id;
                $content_type = $order->content_type;
                $content_title = $order->content_title;
                $content_indexpic = $order->content_indexpic;
                $shop_id = $order->shop_id;


                foreach ($member_order as $item) {
                    $is_payment = Payment::where(['content_id'=>$content_id,'content_type'=>$content_type,'user_id'=>$item->user_id])->value('id');
                    if ($item->user_id && $item->order_id && !$is_payment) {
                        $payment[] = [
                            'user_id' => $item->user_id,
                            'nickname' => $item->nickname,
                            'avatar' => $item->avatar,
                            'payment_type' => 1,
                            'content_id' => $content_id,
                            'content_type' => $content_type,
                            'content_title' => $content_title,
                            'content_indexpic' => $content_indexpic,
                            'order_id' => $item->order_id,
                            'order_time' => $item->pay_time,
                            'price' => $item->price,
                            'shop_id' => $shop_id,
                        ];

                        switch ($content_type) {
                            case 'column':
                            case 'course':
                                Cache::forever('payment:' . $shop_id . ':' . $item->user_id . ':' . $content_id . ':' . $content_type, $item->order_id);
                                break;
                            case 'member_card':
                                event(new CreateCardRecord($item));
                                Cache::forever('payment:' . $shop_id . ':' . $item->user_id . ':' . $content_id . ':' . $content_type, $item->order_id);
                                break;
                            default :
                                Cache::forever('payment:' . $shop_id . ':' . $item->user_id . ':' . $content_id, $item->order_id);
                                break;
                        }

                        $content_type == 'column' && $this->saveContentId(['content_id' => $content_id, 'shop_id' => $shop_id], $item->user_id);

                        event(new SubscribeEvent($item->content_id, $item->content_type, $item->shop_id, $item->user_id, 1)); //同$payment payment_type
                        event(new PayEvent($item));
                        event(new SalesTotalEvent($order));
                        //更新推广记录表订单状况
                        $this->savePromoterRecord($item);
                    }
                }
                $payment && Payment::insert($payment); //

                //更新订单中心订单信息
                if($member_order->pluck('center_order_no')->isNotEmpty()){
                    foreach ($member_order->pluck('center_order_no') as $order_no){
                        $appId = config('define.order_center.app_id');
                        $appSecret = config('define.order_center.app_secret');
                        $timesTamp = time();
                        $param = [
                            'extra_data'    => [
                                'fight_group_id' => $fight_group_id
                            ]
                        ];
                        $client = hg_verify_signature($param,$timesTamp,$appId,$appSecret,$order->shop_id);
                        try{
                            $url = str_replace('{order_no}', $order_no, config('define.order_center.api.m_order_confirm'));
                            $res = $client->request('PUT',$url);
                            $return = $res->getBody()->getContents();
                            event(new CurlLogsEvent($return,$client,$url));
                        }catch (\Exception $exception){
                            event(new ErrorHandle($exception,'order_center'));
                        }
                    }

                }
            }

        }
    }
    private function saveContentId($data,$user_id){
        $content_id = Column::where(['column.hashid'=>$data['content_id'],'column.shop_id'=>$data['shop_id']])
            ->leftJoin('content','content.column_id','=','column.id')
            ->where('content.payment_type',1)->pluck('content.hashid')->toArray();
        if($content_id){
            Redis::sadd('subscribe:h5:'.$data['shop_id'].':'.$user_id,$content_id);
        }
    }

    private function savePromoterRecord($order)
    {
        $promoterRecord = PromotionRecord::where(['order_id'=>$order->order_id,'state'=>0,'promotion_type'=>'promotion'])->first();
        if ($promoterRecord) {
            $promoterRecord->state = 1;
            $promoterRecord->finish_time = time();
            $promoterRecord->save();
            //更新邀请推广记录状态
            $visit_record = PromotionRecord::where(['order_id'=>$order->order_id,'state'=>0,'promotion_type'=>'visit'])->first();
            if($visit_record){
                $visit_record->state = 1;
                $visit_record->finish_time = time();
                $visit_record->save();
            }

        }
    }

}
