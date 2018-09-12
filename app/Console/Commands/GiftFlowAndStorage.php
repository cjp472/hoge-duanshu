<?php
/**
 * 每月赠送流量和存储空间
 * User: tanqiang
 * Date: 2018/6/14
 * Time: 17:52
 */

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\ShopStorageFluxFree;
use App\Models\SystemNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GiftFlowAndStorage extends Command
{
    protected $signature = 'gift:flux:storage {--shop_id=} {--now=}';
    protected $description = '每月第一天赠送存储空间和流量';

    public function handle()
    {
        //基础版用户赠送流量
        if(defined('DEFAULT_BASE_STORAGE') && defined('DEFAULT_BASE_FLOW')){
            $shop_id = $this->option('shop_id');
            $now = $this->option('now') ?: time();
            $month = date('Y-m', $now);
            $start_time = strtotime(date('Y-m-01', $now));
            $end_time = strtotime('1 months', $start_time);
            $key = 'shop:gift:flux:storage:month:'.$month;
            $time = 24 * 60 * 60;
            if(!Redis::set($key, 1, "nx", "ex", $time)){
                //获取不到锁就return 保证每月第一天只执行一次
                return;
            }

            Redis::setex($key.":start:", $time, date('Y-m-d H:i:s'));

            if($shop_id){
                $shops = Shop::where('shop.hashid',$shop_id)
                    ->where('shop.version',VERSION_BASIC)
                    ->select('shop.hashid')
                    ->join('shop_disable', function($join){
                        $join->on('shop.hashid','=','shop_disable.shop_id')->where(['shop_disable.disable'=>1]);
                    },'','','left')
                    ->whereNull('shop_disable.id')
                    ->join('shop_funds_arrears', function($join){
                        $join->on('shop.hashid','=','shop_funds_arrears.shop_id')->where(['shop_funds_arrears.status'=>1]);
                    },'','','left')
                    ->whereNull('shop_funds_arrears.id')
                    ->orderBy('shop.id', 'asc')
                    ->get();
                $this->giftStorageFlux($shops, $start_time, $end_time);
            }else{
                $page = 1;
                $count = 1000;
                $shop_ids = [];
                while (1) {
                    $offset = ($page - 1) * $count;
                    $shops = Shop::where('shop.version',VERSION_BASIC)
                        ->select('shop.hashid')
                        ->join('shop_disable', function($join){
                            $join->on('shop.hashid','=','shop_disable.shop_id')->where(['shop_disable.disable'=>1]);
                        },'','','left')
                        ->whereNull('shop_disable.id')
                        ->join('shop_funds_arrears', function($join){
                            $join->on('shop.hashid','=','shop_funds_arrears.shop_id')->where(['shop_funds_arrears.status'=>1]);
                        },'','','left')
                        ->whereNull('shop_funds_arrears.id')
                        ->orderBy('shop.id', 'asc')
                        ->offset($offset)
                        ->limit($count)
                        ->get();
                    foreach ($shops as $shop){
                        if($shop){
                            $shop_ids[] = $shop->hashid;
                        }
                    }
                    if (count($shops) < $count)
                        break;
                    $page++;
                }
                $frees = ShopStorageFluxFree::where(['start_time'=>$start_time, 'end_time'=>$end_time])
                    ->select('shop_id', 'type')
                    ->get();
                $free_exists = [];
                foreach ($frees as $free) {
                    if($free->shop_id){
                        $free_exists[]= $free->shop_id.'__'.$free->type;
                    }
                }
                echo count($shop_ids)."\n";
                echo count($free_exists)."\n";
                $this->giftStorageFlux($shop_ids, $free_exists, $start_time, $end_time);
            }

            Redis::setex($key.":end:", $time, date('Y-m-d H:i:s'));
        }

        //记录脚本执行时间
        file_put_contents(storage_path('logs/giftlog.txt'),date('Y-m-d H:i:s'));
    }

    private function giftStorageFlux($shop_ids, $free_exists, $start_time, $end_time){
        if($shop_ids){
            $shop_notice_ids = [];
            foreach ($shop_ids as $shop_id) {
                $storage_exist = in_array($shop_id.'__'.QCOUND_COS, $free_exists);
                $flux_exist = in_array($shop_id.'__'.QCOUND_CDN, $free_exists);
                if(!$storage_exist){
                    $params[] = [
                        'shop_id' => $shop_id,
                        'value' => DEFAULT_BASE_STORAGE,
                        'type'=>QCOUND_COS,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                    ];
                }

                if(!$flux_exist){
                    $params[] = [
                        'shop_id' => $shop_id,
                        'value' => DEFAULT_BASE_FLOW,
                        'type'=>QCOUND_CDN,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                    ];
                }
                if(!$storage_exist && !$flux_exist){
                    $shop_notice_ids[] = $shop_id;
                }
            }
            if(isset($params) && count($params)>0){
                ShopStorageFluxFree::insert($params);
                $storage = number_format(DEFAULT_BASE_STORAGE / (1024 * 1024), 2, '.', '');
                $flux = number_format(DEFAULT_BASE_FLOW / (1024 * 1024), 2, '.', '');
                SystemNotice::sendShopsSystemNotice($shop_notice_ids, 'notice.title.storage_flux_free',
                    'notice.content.storage_flux_free', ['storage'=>$storage, 'flux'=>$flux]);
                echo count($params)."\n";
                echo count($shop_notice_ids)."\n";
            }
        }
    }
}