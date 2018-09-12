<?php
/**
 * 短书币欠费处理 只有线上才会跑任务
 * User: tanqiang
 * Date: 2018/6/14
 * Time: 17:52
 */

namespace App\Console\Commands;

use App\Events\SendMessageEvent;
use App\Models\Shop;
use App\Models\ShopDisable;
use App\Models\ShopFunds;
use App\Models\ShopFundsArrears;
use App\Models\ShopNotice;
use App\Models\SystemNotice;
use App\Models\UserShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FundsArrearsCommond extends Command
{
    protected $description = '短书币欠费1-3天内,每天发送通知+短信提示, 超过3天关闭店铺, 命令每天执行一次';
    /**
     * 2018-07-16
     * php artisan funds:arrears --now=1531735201
     */

    protected $signature = 'funds:arrears {--now=}';

    public function handle()
    {
        $date = date('Y-m-d');
        $key = 'shop:funds:arrears:date:' . $date;
        $time = 24 * 60 * 60;
        if(!Redis::set($key, 1, "nx", "ex", $time)){
            //获取不到锁就return 保证一天只执行一次
            return;
        }
        Redis::setex($key.":start:", $time, date('Y-m-d H:i:s'));
        $this->staticShopFundsArrears();
        Redis::setex($key.":end:", $time, date('Y-m-d H:i:s'));
    }

    /**
     * 发送欠费短信
     * @param $shop_id
     * @param $max_amount
     * @param $year
     * @param $month
     * @param $day
     */
    protected function sendArrearsMessage($shop_id, $max_amount, $year, $month, $day)
    {
        $user_shop = UserShop::where(['shop_id' => $shop_id, 'admin' => 1])->first();
        $mobile = $user_shop->user ? $user_shop->user->mobile : '';
        $mobile && event(new SendMessageEvent($mobile, 'duanshu-shop-arrears', ['num' => $max_amount, 'year' => $year, 'month' => $month, 'day' => $day]));
    }

    /**
     * 发送打烊短信
     * @param $shop_id
     */
    protected function sendShopCloseMessage($shop_id)
    {
        $shop = Shop::where(['hashid' => $shop_id])->first();
        $user_shop = UserShop::where(['shop_id' => $shop_id, 'admin' => 1])->first();
        $mobile = $user_shop->user ? $user_shop->user->mobile : '';
        $mobile && event(new SendMessageEvent($mobile, 'duanshu-shop-close', ['shop' => $shop->title]));
    }

    /**
     * 先统计欠费情况
     */
    protected function staticShopFundsArrears()
    {
        $page = 1;
        $count = 1000;
        $time = time();
        $datetime = date('Y-m-d H:i:s');
        while (1) {
            $offset = ($page - 1) * $count;
            $query_set = ShopFunds::select(['shop_funds.shop_id', 'shop_funds_arrears.id as funds_arrears_id',
                'shop_funds_arrears.start_time', 'shop_funds_arrears.max_amount'])
                ->selectRaw('sum(hg_shop_funds.amount) as sum_balance')
                ->join('shop_funds_arrears', function ($join) {
                    $join->on('shop_funds.shop_id', 'shop_funds_arrears.shop_id')
                        ->where('shop_funds_arrears.status', 1);
                }, '', '', 'left')
                ->where('shop_funds.status', 0)
                ->groupBy('shop_id')
                ->orderBy('shop_id')
                ->offset($offset)
                ->limit($count)
                ->get();
            $shop_ids = [];
            foreach ($query_set as $item) {
                $shop_id = $item->shop_id;
                $shop_ids[] = $shop_id;
            }
            $shops = Shop::whereIn('hashid', $shop_ids)->select(['hashid', 'version'])->get();
            $shop_map = [];
            foreach ($shops as $shop) {
                $shop_map[$shop->hashid] = $shop;
            }
            $funds_arrears_exist = [];
            $funds_arrears_not_exist = [];
            $notice_ids = [];
            $content_params = [];
            $shop_disable_ids = [];
//            $sms_params = [];
            foreach ($query_set as $item) {
                $shop_id = $item->shop_id;
                if (!isset($shop_map[$shop_id])) {
                    //未找到店铺信息
                    continue;
                }
                $shop = $shop_map[$shop_id];
                if ($shop->version != VERSION_BASIC) {
                    //不为基础版本
                    if ($item->funds_arrears_id) {
                        $funds_arrears_exist[] = [
                            'id' => $item->funds_arrears_id,
                            'max_amount' => $item->max_amount,
                            'status' => 0,
                            'end_time' => time()
                        ];
                    }
                    continue;
                }
                $start_time = $time;
                if ($item->funds_arrears_id) {
                    //已经存在欠费记录
                    if ($item->sum_balance >= 0) {
                        $funds_arrears_exist[] = [
                            'id' => $item->funds_arrears_id,
                            'max_amount' => $item->max_amount,
                            'status' => 0,
                            'end_time' => time()
                        ];
                        continue;
                    }
                    $max_amount = $item->max_amount;
                    if ($max_amount > $item->sum_balance) {
                        $max_amount = $item->sum_balance;
                        $funds_arrears_exist[] = [
                            'id' => $item->funds_arrears_id,
                            'max_amount' => $max_amount,
                            'status' => 1,
                            'end_time' => 0,
                        ];
                    }
                    $start_time = $item->start_time;
                } else {
                    if ($item->sum_balance >= 0) {
                        continue;
                    } else {
                        $funds_arrears_not_exist[] = [
                            'shop_id' => $shop_id,
                            'status' => 1,
                            'start_time' => $time,
                            'max_amount' => $item->sum_balance,
                            'created_at' => $datetime,
                            'updated_at' => $datetime,
                        ];
                    }
                }
                $days = round(($time - $start_time) / 86400);
                if ($days >= 3) {
                    $shop_disable_ids[] = $shop_id;
                } else {
                    $notice_ids[] = $shop_id;
                    $num = (3 - $days);
//                    $disable_times = $time + $num * (24 * 60 * 60);
//                    $y = date('Y', $disable_times);
//                    $m = date('m', $disable_times);
//                    $d = date('d', $disable_times);
                    $content_params[] = [
                        'num' => (3 - $days),
                    ];
//                    $sms_params[] = [
//                        'num'=> $arrears_amount,
//                        'year'=> $y,
//                        'month'=> $m,
//                        'day'=> $d,
//                    ];
                }
            }

            //记录欠费
            if (isset($funds_arrears_not_exist) && count($funds_arrears_not_exist) > 0) {
                ShopFundsArrears::insert($funds_arrears_not_exist);
            }
            //更新欠费
            if (isset($funds_arrears_exist) && count($funds_arrears_exist) > 0) {
                ShopFundsArrears::updateBatch($funds_arrears_exist);
            }
            //发送通知
            if (isset($notice_ids) && count($notice_ids) > 0) {
                SystemNotice::sendShopsSystemNoticeMulti($notice_ids, 'notice.title.score.no_money',
                    'notice.content.score.no_money', $content_params);
            }
            if (isset($shop_disable_ids) && count($shop_disable_ids) > 0) {
                $this->shopDisable($shop_disable_ids, $datetime);
            }
            if (count($query_set) < $count)
                break;
            $page++;
        }
    }

//    private function sendFundsArrearsMessage($shop_ids, $params){
//        $disable_ids = ShopDisable::whereIn('shop_id', $shop_ids)
//            ->where('disable', 0)
//            ->groupBy('shop_id')
//            ->pluck('shop_id')->toArray();
//
//        $shop_mobile_map = UserShop::whereIn('user_shop.shop_id', $shop_ids)
//                    ->where('user_shop.admin', 1)
//                    ->leftJoin('users', 'users.id', 'user_shop.user_id')
//                    ->pluck('mobile', 'shop_id')->toArray();
//        $count = count($shop_ids);
//        for ($i = 0; $i < $count; $i++) {
//            $shop_id = $shop_ids[$i];
//            if (!in_array($shop_id, $disable_ids) && isset($shop_mobile_map[$shop_id])) {
//                $sms_params[] = [
//                    'template' => 'duanshu-shop-arrears',
//                    'kwargs' => [
//                        $params[$i]
//                    ],
//                    'mobile' => $shop_mobile_map[$shop_id],
//                ];
//            }
//        }
//    }

    private function shopDisable($shop_ids, $datetime)
    {
        $disable_ids = ShopDisable::whereIn('shop_id', $shop_ids)
            ->where(['disable' => 1, 'type' => SHOP_DISABLE_FUNDS_ARREARS])
            ->groupBy('shop_id')
            ->pluck('shop_id')->toArray();
        $count = count($shop_ids);
        $disable_params = [];
        $disable_shop_ids = [];
        $shop_notice_params = [];
        for ($i = 0; $i < $count; $i++) {
            $shop_id = $shop_ids[$i];
            if (!in_array($shop_id, $disable_ids)) {
                $disable_params[] = [
                    'shop_id' => $shop_id,
                    'disable' => 1,
                    'type' => SHOP_DISABLE_FUNDS_ARREARS,
                    'source' => getenv('APP_ENV'),
                    'created_at' => $datetime,
                    'updated_at' => $datetime,
                ];
                $shop_notice_params[] = [
                    'shop_id' => $shop_id,
                    'type' => SHOP_DISABLE_FUNDS_ARREARS,
                    'content' => trans('notice.content.score.shop_disable_funds_arrears'),
                    'status' => 1,
                    'source' => getenv('APP_ENV'),
                    'created_at' => $datetime,
                    'updated_at' => $datetime,
                ];
                $disable_shop_ids[] = $shop_id;
            }
        }
        if (isset($disable_shop_ids) && count($disable_shop_ids) > 0) {
            SystemNotice::sendShopsSystemNotice($disable_shop_ids, 'notice.title.shop_disable',
                'notice.content.score.shop_disable_funds_arrears');
        }
        if (isset($disable_params) && count($disable_params) > 0) {
            ShopDisable::insert($disable_params);
        }
        if (isset($shop_notice_params) && count($shop_notice_params) > 0) {
            ShopNotice::insert($shop_notice_params);
        }
    }
}