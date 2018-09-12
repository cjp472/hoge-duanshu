<?php
namespace App\Listeners;

use App\Models\Promotion;
use App\Models\PromotionRecord;
use App\Models\PromotionContent;
use App\Events\PromoterRecordEvent;
use App\Models\PromotionShop;

class SyncPromoterRecord
{
    public function handle(PromoterRecordEvent $event)
    {
        $orderInfo = PromotionRecord::where(['order_id'=>$event->order->order_id,'state'=>0])->first();
        $promotionStatus = hg_check_promotion($event->promoterId,$event->order->shop_id);
        $visitId = $promotionStatus ? $promotionStatus->visit_id : '';
        if($visitId){
            $visitStatus = hg_check_promotion($visitId,$event->order->shop_id);
            if(!$visitStatus){
                $visitId = '';
            }
        }
        $promoterContent = PromotionContent::select('money_percent','visit_percent')->where(['shop_id'=>$event->order->shop_id,'content_id'=>$event->order->content_id,'content_type'=>$event->order->content_type])->first();
        $percent = PromotionShop::select('money_percent','visit_percent')->where('shop_id',$event->order->shop_id)->first();
        if(!$orderInfo){
            $data = [
                'order_id' => $event->order->order_id,
                'shop_id'  => $event->order->shop_id,
                'promotion_id' => $event->promoterId,
                'visit_id' => $visitId ? : null,
                'buy_id'   => $event->order->user_id,
                'content_id' => $event->order->content_id,
                'content_type' => $event->order->content_type,
                'content_title' => $event->order->content_title,
                'deal_money' => $event->order->price,
                'money_percent' => $promoterContent->money_percent == -1 ? $percent->money_percent : $promoterContent->money_percent,
                'visit_percent' => $promoterContent->visit_percent == -1 ? $percent->visit_percent : $promoterContent->visit_percent,
                'state' => 0,
                'create_time' => time()
            ];
            PromotionRecord::insert($data);
        }else{
            $orderInfo->visit_id = $visitId ? : null;
            $orderInfo->content_title = $event->order->content_title;
            $orderInfo->deal_money = $event->order->price;
            $orderInfo->money_percent = $promoterContent->money_percent == -1 ? $percent->money_percent : $promoterContent->money_percent;
            $orderInfo->visit_percent = $promoterContent->visit_percent == -1 ? $percent->visit_percent : $promoterContent->visit_percent;
            $orderInfo->create_time = time();
            $orderInfo->save();
        }
        //如果是邀请人，同样要新增一条推广记录
        if($visitId){
            $visit_record_order = PromotionRecord::where(['order_id'=>$event->order->order_id,'state'=>0,'promotion_type'=>'visit'])->first();
            if(!$visit_record_order){
                $visit_data = [
                    'order_id' => $event->order->order_id,
                    'shop_id'  => $event->order->shop_id,
                    'promotion_id' => $visitId,
                    'visit_id' => null,
                    'buy_id'   => $event->order->user_id,
                    'content_id' => $event->order->content_id,
                    'content_type' => $event->order->content_type,
                    'content_title' => $event->order->content_title,
                    'deal_money' => $event->order->price,
                    'money_percent' => $promoterContent->visit_percent == -1 ? $percent->visit_percent : $promoterContent->visit_percent,
                    'visit_percent' => 0,
                    'state' => 0,
                    'create_time' => time(),
                    'promotion_type' => 'visit'
                ];
                PromotionRecord::insert($visit_data);
            }else{
                $visit_record_order->content_title = $event->order->content_title;
                $visit_record_order->deal_money = $event->order->price;
                $visit_record_order->money_percent = $promoterContent->visit_percent == -1 ? $percent->visit_percent : $promoterContent->visit_percent;
                $visit_record_order->visit_percent = 0;
                $visit_record_order->create_time = time();
                $visit_record_order->save();
            }
        }
    }
}