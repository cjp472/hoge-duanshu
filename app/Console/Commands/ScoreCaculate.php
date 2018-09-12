<?php
/**
 * Created by PhpStorm.
 * User: Janice
 * Date: 2018/6/22
 * Time: 19:08
 */

namespace App\Console\Commands;


use App\Events\SettlementEvent;
use App\Models\ShopFlow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ScoreCaculate extends Command
{
    protected $signature = 'caculate:score';

    protected $description = '每日结算流量费用';

    public function handle()
    {
        //取昨日的时间
        $yestoday = strtotime(date('Y-m-d', strtotime("-1 day")));

        //查出昨日有统计数据的店铺id
        $shop_ids = ShopFlow::where('time', $yestoday)
            ->select('shop_id')
            ->where('source',getenv('APP_ENV'))
            ->distinct()
            ->get();

        if ($shop_ids) {
            foreach ($shop_ids->all() as $shop) {
                $rKey = getenv('APP_ENV').'flowcron:' . $yestoday . ':' . $shop->shop_id;
                //如果没有统计过该店铺
                if (!Redis::exists($rKey)) {
                    //触发计算的事件
                    event(new SettlementEvent($shop->shop_id,$yestoday));
                    Redis::set($rKey,1);
                }
            }
        }
    }
}