<?php

namespace App\Jobs;

use App\Events\Content\StaticsEvent;
use App\Models\ContentStatistics;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Models\Views;
use App\Models\Order;

class CronContentData
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $timeValue;

    public function __construct($data)
    {
        $this->timeValue = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(){
        $timeValue = $this->timeValue;
        $beginYesterday = $timeValue['beginYesterday'];
        $endYesterday = $timeValue['endYesterday'];

        $this->totalContent($beginYesterday,$endYesterday);
        $this->userContent($beginYesterday,$endYesterday);
    }

    private function totalContent($beginYesterday,$endYesterday)
    {
        $income = Order::where('pay_status',1)
            ->whereBetween('pay_time',[$beginYesterday,$endYesterday])
            ->selectRaw('sum(price) as income,content_type')
            ->groupBy('content_type')
            ->pluck('income','content_type');
        $order = Order::where('pay_status',1)
            ->whereBetween('pay_time',[$beginYesterday,$endYesterday])
            ->groupBy('content_type')
            ->selectRaw('count(id) as total,content_type')
            ->pluck('total','content_type');
        //总阅读数
        $clickNum = Views::whereBetween('view_time',[$beginYesterday,$endYesterday])
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
                    'shop_id' => 'total',
                ];

                $cs = new ContentStatistics($content);
                $cs->save();
            }
        }
    }


    private function userContent($beginYesterday,$endYesterday)
    {
        $shops = Shop::pluck('hashid');
        if($shops)
        {
            foreach ($shops as $shop){
                event(new StaticsEvent($shop,$beginYesterday,$endYesterday));
            }
        }
    }
}
