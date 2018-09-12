<?php
namespace App\Listeners\Content;

use App\Events\Content\StaticsEvent;
use App\Models\ContentStatistics;
use App\Models\Order;
use App\Models\Views;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class StaticsListener implements ShouldQueue
{
    use InteractsWithQueue;
    public $queue = DEFAULT_QUEUE;

    public function handle(StaticsEvent $event)
    {
        $shop = $event->shop_id;
        $beginYesterday = $event->begin;
        $endYesterday = $event->end;
        $income = Order::where('pay_status',1)
            ->whereBetween('pay_time',[$beginYesterday,$endYesterday])
            ->where('shop_id',$shop)
            ->selectRaw('sum(price) as income,content_type')
            ->groupBy('content_type')
            ->pluck('income','content_type');
        $order = Order::where('pay_status',1)
            ->where('shop_id',$shop)
            ->whereBetween('pay_time',[$beginYesterday,$endYesterday])
            ->groupBy('content_type')
            ->selectRaw('count(id) as total,content_type')
            ->pluck('total','content_type');
        //总阅读数
        $clickNum = Views::whereBetween('view_time',[$beginYesterday,$endYesterday])
            ->where('shop_id',$shop)
            ->groupby('content_type')
            ->selectRaw('count(id) as total,content_type')
            ->pluck('total','content_type');

        if( $income || $order || $clickNum){
            $income_col = collect($income);
            $order_col = collect($order);
            $click_col = collect($clickNum);
            $keys = $income_col->keys();
            foreach ($keys as $item){
                $content = [
                    'type' => $item,
                    'create_time' => $beginYesterday,
                    'yesterday_income' => $income_col->has($item) ? $income_col->get($item) : 0.00,
                    'click_num' => $click_col->has($item) ? $click_col->get($item) : 0,
                    'order_num' => $order_col->has($item) ? $order_col->get($item) : 0,
                    'year' => date('Y',$beginYesterday),
                    'month' => date('m',$beginYesterday),
                    'day' => date('d',$beginYesterday),
                    'week' => date('W',$beginYesterday),
                    'shop_id' => $shop,
                ];

                $cs = new ContentStatistics($content);
                $cs->save();
            }
        }
    }
}