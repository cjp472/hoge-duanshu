<?php

namespace App\Console\Commands;

use App\Events\SendMessageEvent;
use App\Models\Shop;
use App\Models\ShopDisable;
use App\Models\ShopNotice;
use App\Models\SystemNotice;
use App\Models\UserShop;
use App\Models\VersionExpire;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ShopExpireSettlement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:expire {--shop_id=} {--now=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'close shop when expired';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $shop_id = $this->option('shop_id');
        $now = $this->option('now')? :time();
        $date = date('Y-m-d', $now);
        $key = 'shop:expire:settlement:date:'.$date;
        $time = 24 * 60 * 60;
        if(!Redis::set($key, 1, "nx", "ex", $time)){
            //获取不到锁就return 保证一天只执行一次
            return;
        }
        Redis::setex($key.":start:", $time, date('Y-m-d H:i:s'));
        $shop_ids = [];
        if ($shop_id) {
            $shop_ids[] = $shop_id;
            $this->settlement($shop_ids, $now);
        } else {
            $page = 1;
            $count = 1000;
            while (1) {
                $offset = ($page - 1) * $count;
                $query_set = Shop::select('shop.hashid')
                    ->join('shop_disable', function ($join) {
                        $join->on('shop.hashid', '=', 'shop_disable.shop_id')
                            ->where(['shop_disable.disable' => 1])
                            ->whereIn('shop_disable.type', [SHOP_DISABLE_ADVANCED_EXPIRE,
                                SHOP_DISABLE_TEST_ADVANCED_EXPIRE, SHOP_DISABLE_BASIC_EXPIRE,
                                SHOP_DISABLE_BASIC_TEST_EXPIRE, SHOP_DISABLE_PARTNER_EXPIRE]);
                    }, '', '', 'left')
                    ->whereNull('shop_disable.id')
                    ->orderBy('shop.id', 'asc')
                    ->orderBy('shop.create_time', 'asc')
                    ->offset($offset)
                    ->limit($count)
                    ->get();
                if ($query_set && ($len = count($query_set)) > 0) {
                    for ($i = 0; $i < $len; $i++) {
                        $query = $query_set[$i];
                        if (!$query->hashid)
                            continue;
                        $shop_ids[] = $query->hashid;
                    }
                }
                if (count($query_set) < $count)
                    break;
                $page++;
            }
            $settlement_ids = [];
            if (isset($shop_ids) && count($shop_ids) > 0) {
                $count = count($shop_ids);
                for ($i = 1; $i <= $count; $i++) {
                    $settlement_ids[] = $shop_ids[$i - 1];
                    if ($i % 1000 == 0 || $i == $count) {
                        $this->settlement($settlement_ids, $now);
                        $settlement_ids = [];
                    }
                }
            }
        }
        Redis::setex($key.":end:", $time, date('Y-m-d H:i:s'));
    }

    const NOTICE_TYPE = [
        SHOP_DISABLE_FUNDS_ARREARS => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.score.shop_disable_funds_arrears',
        ],
        SHOP_DISABLE_BASIC_EXPIRE => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.verify.shop_disable_basic_expire',
        ],
        SHOP_DISABLE_BASIC_TEST_EXPIRE => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.verify.shop_disable_basic_test_expire',
        ],
        SHOP_DISABLE_STANDARD_EXPIRE => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.shop_disable_standard_expire',
        ],
        SHOP_DISABLE_ADVANCED_EXPIRE => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.shop_disable_advanced_expire',
        ],
    ];


    private function settlement($shop_ids, $now){
        $query_set = VersionExpire::select(['version_expire.hashid', 'version_expire.expire', 'shop.version', 'shop.verify_expire'])
            ->whereIn('version_expire.hashid', $shop_ids)
            ->where('is_expire', 0)
            ->leftJoin('shop', 'shop.hashid', 'version_expire.hashid')
            ->whereColumn('version_expire.version', 'shop.version')
            ->orderBy('version_expire.expire', 'desc');
        $db = app('db');
        $datetime = date('Y-m-d H:i:s');
        $items = $db->table($db->raw("({$query_set->toSql()}) as sub"))->mergeBindings($query_set->getQuery())
            ->groupBy('hashid')->get();
        //二十九天后
        $twenty_nine_days_later = strtotime('+29 days',$now);
        //三十天后
        $thirty_days_later = strtotime('+30 days',$now);
        //6天后
        $six_days_later = strtotime('+6 days',$now);
        //7天后
        $seven_days_later = strtotime('+7 days',$now);
        //3天后
        $third_days_later = strtotime('+3 days',$now);
        //2天后
        $two_days_later = strtotime('+2 days',$now);
        //1天后
        $one_days_later = strtotime('+1 days',$now);
        $shop_basic_notice_ids = [];
        $shop_basic_notice_content_params = [];
        $shop_disable_basic = [];
        $shop_disable_test_basic = [];
        $basic_expire_ids = [];
        $shop_standard_notice_ids = [];
        $shop_standard_notice_content_params = [];
        $standard_expire_ids = [];
        $shop_advanced_notice_ids = [];
        $shop_advanced_notice_content_params = [];
        $advanced_expire_ids = [];
        $shop_exist_ids = [];
        foreach ($items as $item){
            $shop_id = $item->hashid;
            $version = $item->version;
            $expire = $item->expire;
            $shop_exist_ids[] = $shop_id;
            switch ($version){
                case VERSION_BASIC:
                    $verify_expire = $item->verify_expire;
                    if($verify_expire > 0 && $expire>=$six_days_later && $expire < $seven_days_later){
                        $shop_basic_notice_ids[] = $shop_id;
                        $shop_basic_notice_content_params[] = $this->getTimeParams($seven_days_later);
                    } else if($expire<=$now){
                        if($verify_expire > 0){
                            $shop_disable_basic[] = $shop_id;
                        } else{
                            //未购买过认证
                            $shop_disable_test_basic[] = $shop_id;
                        }
                        $basic_expire_ids[] = $shop_id;
                    }
                    break;
                case VERSION_STANDARD:
                    if (($expire >= $twenty_nine_days_later && $expire < $thirty_days_later)
                        || ($expire < $seven_days_later && $expire > $now)){
                        $shop_standard_notice_ids[] = $shop_id;
                        $shop_standard_notice_content_params[] = $this->getTimeParams($expire);
                    }  else if($expire<=$now){
                        $standard_expire_ids[] = $shop_id;
                    }
                    break;
                case VERSION_ADVANCED:
                    if (($expire >= $twenty_nine_days_later && $expire < $thirty_days_later)
                        || ($expire < $seven_days_later && $expire > $now)){
                        $shop_advanced_notice_ids[] = $shop_id;
                        $shop_advanced_notice_content_params[] = $this->getTimeParams($expire);
                    }  else if($expire<=$now){
                        $advanced_expire_ids[] = $shop_id;
                    }
                    break;
                default:
                    break;
            }
        }
        //处理已过期没有打烊的店铺
        $shop_not_exist_ids = array_diff($shop_ids, $shop_exist_ids);
        if(count($shop_not_exist_ids) > 0){
            $query_set = Shop::select(['hashid', 'version', 'verify_expire'])
                ->whereIn('hashid', $shop_not_exist_ids)
                ->get();
            foreach ($query_set as $item){
                $shop_id = $item->hashid;
                $version = $item->version;
                switch ($version){
                    case VERSION_BASIC:
                        $verify_expire = $item->verify_expire;
                        if($verify_expire > 0){
                            $shop_disable_basic[] = $shop_id;
                        } else{
                            //未购买过认证
                            $shop_disable_test_basic[] = $shop_id;
                        }
                        $basic_expire_ids[] = $shop_id;
                        break;
                    case VERSION_STANDARD:
                        $standard_expire_ids[] = $shop_id;
                        break;
                    case VERSION_ADVANCED:
                        $advanced_expire_ids[] = $shop_id;
                        break;
                    default:
                        echo 'version:'.$version."\n";
                        break;
                }
            }
        }
        // 基础版即将到期通知
        if ($shop_basic_notice_ids && count($shop_basic_notice_ids) > 0) {
            SystemNotice::sendShopsSystemNoticeMulti($shop_basic_notice_ids, 'notice.title.shop_basic_expire',
                'notice.content.shop_basic_expire', $shop_basic_notice_content_params);
        }
        // 基础版到期处理
        if($shop_disable_basic && count($shop_disable_basic)){
            $this->setVersionExpire($shop_disable_basic, SHOP_DISABLE_BASIC_EXPIRE, $datetime);
        }
        // 基础版试用到期处理
        if($shop_disable_test_basic && count($shop_disable_test_basic)){
            $this->setVersionExpire($shop_disable_test_basic, SHOP_DISABLE_BASIC_TEST_EXPIRE, $datetime);
        }
        // 标准版即将到期通知
        if ($shop_standard_notice_ids && count($shop_standard_notice_ids) > 0) {
            SystemNotice::sendShopsSystemNoticeMulti($shop_standard_notice_ids, 'notice.title.shop_standard_almost_expire',
                'notice.content.shop_standard_almost_expire', $shop_standard_notice_content_params);
        }
        // 标准版到期处理
        if($standard_expire_ids && count($standard_expire_ids)){
            $this->setVersionExpire($standard_expire_ids, SHOP_DISABLE_STANDARD_EXPIRE, $datetime);
        }
        // 高级版即将到期通知
        if ($shop_advanced_notice_ids && count($shop_advanced_notice_ids) > 0) {
            SystemNotice::sendShopsSystemNoticeMulti($shop_advanced_notice_ids, 'notice.title.shop_advanced_almost_expire',
                'notice.content.shop_advanced_almost_expire', $shop_advanced_notice_content_params);
        }
        // 高级版到期处理
        if($advanced_expire_ids && count($advanced_expire_ids)){
            $this->setVersionExpire($advanced_expire_ids, SHOP_DISABLE_ADVANCED_EXPIRE, $datetime);
        }

        // 设置基础版版本到期
        if(isset($basic_expire_ids)){
            VersionExpire::whereIn('hashid', $basic_expire_ids)->where('version', VERSION_BASIC)->update(['is_expire'=>1]);
        }
        // 设置标准版版本到期
        if(isset($standard_expire_ids)){
            VersionExpire::whereIn('hashid', $standard_expire_ids)->where('version', VERSION_STANDARD)->update(['is_expire'=>1]);
        }
        // 设置高级版版本到期
        if(isset($advanced_expire_ids)){
            VersionExpire::whereIn('hashid', $advanced_expire_ids)->where('version', VERSION_ADVANCED)->update(['is_expire'=>1]);
        }
    }

    /**
     * 高级版即将到期通知
     * @param $shop_id
     * @param $time
     */
    public function sendAdvancedNotice($shop_id, $time){
        $y = date('Y', $time);
        $m = date('m', $time);
        $d = date('d', $time);
        SystemNotice::sendShopSystemNotice($shop_id, 'notice.title.shop_advanced_almost_expire',
            'notice.content.shop_advanced_almost_expire', ['year' => $y, 'month' => $m, 'day' => $d]);
//        $this->sendShopAdvancedAlmostExpireSms($shop_id, $y, $m, $d);
    }

    /**
     * 高级版即将到期短信
     * @param $shop_id
     * @param $year
     * @param $month
     * @param $day
     */
    protected function sendShopAdvancedAlmostExpireSms($shop_id, $year, $month, $day)
    {
//        $user_shop = UserShop::where(['shop_id' => $shop_id, 'admin' => 1])->first();
//        $mobile = $user_shop->user ? $user_shop->user->mobile : '';
//        $mobile && event(new SendMessageEvent($mobile, 'duanshu-shop-advanced-almost-expire', ['year' => $year, 'month' => $month, 'day' => $day]));
    }

    /**
     * 基础版到期短信通知
     * @param $shop_id
     * @param $year
     * @param $month
     * @param $day
     */
    protected function sendBasicNotice($shop_id, $time)
    {
        $y = date('Y', $time);
        $m = date('m', $time);
        $d = date('d', $time);
//        SystemNotice::sendShopSystemNotice($shop_id, 'notice.title.shop_basic_expire',
//            'notice.content.shop_basic_expire', ['year' => $y, 'month' => $m, 'day' => $d]);
//        $this->sendBasicExpireSms($shop_id, $y, $m, $d);

    }

    protected function sendBasicExpireSms($shop_id, $year, $month, $day)
    {
//        $user_shop = UserShop::where(['shop_id' => $shop_id, 'admin' => 1])->first();
//        $mobile = $user_shop->user ? $user_shop->user->mobile : '';
//        $mobile && event(new SendMessageEvent($mobile, 'duanshu-shop-basic-almost-expire', ['year' => $year, 'month' => $month, 'day' => $day]));
    }

    protected function getTimeParams($time){
        $y = date('Y', $time);
        $m = date('m', $time);
        $d = date('d', $time);
        return [
            'year' => $y,
            'month' => $m,
            'day' => $d,
        ];
    }

    protected function setVersionExpire($shop_ids, $type, $datetime){
        if($shop_ids && count($shop_ids)){
            $disable_ids = ShopDisable::whereIn('shop_id', $shop_ids)
                ->where(['disable' =>1, 'type'=>$type])
                ->groupBy('shop_id')
                ->pluck('shop_id')->toArray();
            $count = count($shop_ids);
            $disable_params = [];
            $disable_shop_ids = [];
            $shop_notice_params = [];
            $title = self::NOTICE_TYPE[$type]['title'];
            $content = self::NOTICE_TYPE[$type]['content'];
            for ($i = 0; $i < $count; $i++) {
                $shop_id = $shop_ids[$i];
                if (!in_array($shop_id, $disable_ids)) {
                    $disable_params[] = [
                        'shop_id' => $shop_id,
                        'disable' => 1,
                        'type' => $type,
                        'source' =>  getenv('APP_ENV'),
                        'created_at' => $datetime,
                        'updated_at' => $datetime,
                    ];
                    $shop_notice_params[] = [
                        'shop_id' => $shop_id,
                        'type' => $type,
                        'content' => trans($content),
                        'status' => 1,
                        'source' =>  getenv('APP_ENV'),
                        'created_at' => $datetime,
                        'updated_at' => $datetime,
                    ];
                    $disable_shop_ids[] = $shop_id;
                }
            }
            if(isset($disable_shop_ids) && count($disable_shop_ids) > 0){
                SystemNotice::sendShopsSystemNotice($disable_shop_ids, $title, $content);
            }
            if(isset($disable_params) && count($disable_params) > 0){
                ShopDisable::insert($disable_params);
            }
            if(isset($shop_notice_params) && count($shop_notice_params) > 0){
                ShopNotice::insert($shop_notice_params);
            }
        }
    }
}
