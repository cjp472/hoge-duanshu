<?php

namespace App\Listeners;

use App\Events\SalesTotalEvent;
use App\Events\SubscribeEvent;
use App\Models\Column;
use App\Models\Community;
use App\Models\Content;
use App\Models\Course;
use App\Models\MemberCard;
use App\Models\PromotionContent;
use App\Models\PromotionRecord;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SalesTotalListener implements ShouldQueue
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
     * @param  SubscribeEvent $event
     * @return void
     */
    public function handle(SalesTotalEvent $event)
    {
        $shop_id = $event->order->shop_id;
        $content_id = $event->order->content_id;
        $order_id = $event->order->order_id;
        $number = $event->order->number;
        $type = $event->order->content_type;
        switch ($type) {
            case 'column':
                $column = Column::where([
                    'shop_id' => $shop_id,
                    'hashid' => $content_id,
                ])->firstOrFail();
                if ($column) {
                    $column->increment('sales_total', $number);
                }

                $content = Content::where([
                    'shop_id' => $shop_id,
                    'hashid' => $content_id,
                    'type' => 'column',
                ])->first();
                if ($content) {
                    $content->increment('sales_total', $number);
                }
                break;
            case 'course':
                $course = Course::where([
                    'hashid' => $content_id,
                    'shop_id' => $shop_id,
                ])->firstOrFail();
                if ($course) {
                    $course->increment('sales_total', $number);
                }
                $content = Content::where([
                    'shop_id' => $shop_id,
                    'hashid' => $content_id,
                    'type' => 'course',
                ])->first();
                if ($content) {
                    $content->increment('sales_total', $number);
                }
                break;
            case 'member_card':
                $member_card = MemberCard::where([
                    'hashid' => $content_id,
                    'shop_id' => $shop_id,
                ])->first();
                if ($member_card) {
                    $member_card->increment('sales_total', $number);
                }
                break;
            case 'community':
                $community = Community::where([
                    'hashid' => $content_id,
                    'shop_id' => $shop_id,
                ])->first();
                if ($community) {
                    $community->increment('sales_total', $number);
                }
                break;
            default:
                $content = Content::where([
                    'shop_id' => $shop_id,
                    'hashid' => $content_id,
                    'type' => $type,
                ])->first();
                if ($content) {
                    $content->increment('sales_total', $number);
                }
                break;
        }
        $promotion_record = PromotionRecord::where(['shop_id' => $shop_id, 'order_id' => $order_id])->first();
        if ($promotion_record) {
            PromotionContent::where(['shop_id' => $shop_id, 'content_id' => $content_id, 'content_type' => $type])->increment('promotion_sales_total', $number);
        }
    }

    public function failed(SalesTotalEvent $event)
    {
        file_put_contents(storage_path('/logs/faileQueue.txt'), date('Y.m.d H:i:s') . 'sales-total-order-id:' . $event->order->order_id . "\n", FILE_APPEND);
    }
}
