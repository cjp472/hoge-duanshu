<?php
/**
 * 流量存储统计 只有线上才会跑任务
 * User: tanqiang
 * Date: 2018/6/14
 * Time: 17:52
 */

namespace App\Console\Commands;

use App\Events\CurlLogsEvent;
use App\Models\Shop;
use App\Models\ShopFunds;
use App\Models\ShopStorageFlux;
use App\Models\SystemNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class SettlementStorageFlux extends Command
{

    /**
     * 2018-07-16
     * php artisan storage:flux:settlement --shop_id=j54g72862j3630ed1b --now=1531735200
     */
    protected $signature = 'storage:flux:settlement {--shop_id=} {--now=}';
    protected $description = '流量结算';

    public function handle()
    {
        $now = time();
        $this->now_date = date('Y-m-d', $now);
        $now -= 24 * 60 * 60;
        $this->now = $now;
        $this->date = date('Y-m-d', $now);
        $key = 'shop:storage:flux:settlement:date:' . $this->date;
        $time = 24 * 60 * 60;
        if(!Redis::set($key, 1, "nx", "ex", $time)){
            //获取不到锁就return 保证一天只执行一次
            return;
        }
        Redis::setex($key.":start:", $time, date('Y-m-d H:i:s'));
        $this->app_id = config('define.service_store.app_id');
        $this->app_secret = config('define.service_store.app_secret');
        $this->url = config('define.service_store.api.cloudbilling_consume');
        $this->env = getenv('APP_ENV');
        $start_time = date('Y-m-01 00:00:00', $now);
        $time = date_add(date_create($start_time), date_interval_create_from_date_string('1 months'));
        $this->start_time = date('Y-m-01', $now);
        $this->end_time = date_format($time, 'Y-m-d');
        $page = 1;
        $count = 1000;
        while (1) {
            $offset = ($page - 1) * $count;
            $shops = Shop::select(['shop.hashid', 'shop.version', 's.value as storage_free', 'f.value as flux_free'])
                ->join('shop_storage_flux_free as s', function ($join) use ($now) {
                    $join->on('shop.hashid', '=', 's.shop_id')
                        ->where('s.type', QCOUND_COS)
                        ->where('s.start_time', '<=', $now)
                        ->where('s.end_time', '>', $now);
                }, '', '', 'left')
                ->join('shop_storage_flux_free as f', function ($join) use ($now) {
                    $join->on('shop.hashid', '=', 'f.shop_id')
                        ->where('f.type', QCOUND_CDN)
                        ->where('f.start_time', '<=', $now)
                        ->where('f.end_time', '>', $now);
                }, '', '', 'left')
                ->orderBy('shop.create_time', 'asc')
                ->orderBy('shop.id', 'asc')
                ->offset($offset)
                ->limit($count)
                ->get();
            $shop_ids = [];
            $shop_map = [];
            $storage_flux_params = [];
            $funds_params = [];
            $shop_notice_ids = [];
            $shop_funds_not_enough_notice_ids = [];
            foreach ($shops as $shop) {
                if($shop->hashid){
                    $shop_ids[] = $shop->hashid;
                    $shop_map[$shop->hashid] = $shop;
                    $shop->balance = 0;
                    $shop->storage_total = 0;
                    $shop->flux_total = 0;
                }
            }
            if (isset($shop_ids) && count($shop_ids) > 0) {
                $query_set = ShopStorageFlux::whereIn('shop_id', $shop_ids)
                    ->select(['shop_id', 'type'])
                    ->selectRaw('sum(value) as total')
                    ->where('date', '>=', $this->start_time)
                    ->where('date', '<', $this->end_time)
                    ->groupBy('shop_id')
                    ->groupBy('type')
                    ->get();
                foreach ($query_set as $item) {
                    $shop_id = $item->shop_id;
                    $total = $item->total;
                    $type = $item->type;
                    $shop = $shop_map[$shop_id];
                    if ($type == QCOUND_COS) {
                        $shop->storage_total = $total;
                    } else if ($type == QCOUND_CDN) {
                        $shop->flux_total = $total;
                    }
                }
                $shop_array_ids = array_chunk($shop_ids, 100);
                $storage_flux_map = [];
                foreach ($shop_array_ids as $shop_array_id) {
                    $map = $this->getStorageFluxCharge($shop_array_id);
                    if (isset($map) && is_array($map)) {
                        $storage_flux_map = array_merge($storage_flux_map, $map);
                    }
                }
                $query_set = ShopFunds::whereIn('shop_id', $shop_ids)
                    ->select(['shop_id'])
                    ->selectRaw('sum(amount) as balance')
                    ->where('status', 0)
                    ->groupBy('shop_id')
                    ->get();
                foreach ($query_set as $item) {
                    $shop_id = $item->shop_id;
                    $shop = $shop_map[$shop_id];
                    $shop->balance = $item->balance;
                }
                foreach ($storage_flux_map as $k => $v) {
                    $shop = $shop_map[$k];

                    $storage = $v['storage'];
                    $send_notice = false;
                    if ($storage > 0) {
                        $storage_flux_params[] = $this->getStorageFluxParams($k, $storage, QCOUND_COS);
                        $storage_balance = ($shop->storage_free - $shop->storage_total) > 0 ?: 0;
                        $funds_param = $this->getStorageFluxDeduction($shop, $storage_balance, $storage, QCOUND_COS);
                        if ($funds_param)
                            $funds_params[] = $funds_param;
                        $storage_allow = $storage_balance - $storage;
                        $send_notice = ($shop->storage_free > 0 && $storage_allow / $shop->storage_free <= 0.1);
                    }
                    $flux = $v['flux'];
                    if ($flux > 0) {
                        $storage_flux_params[] = $this->getStorageFluxParams($k, $flux, QCOUND_CDN);
                        $flux_balance = ($shop->flux_free - $shop->flux_total) > 0 ?: 0;
                        $funds_param = $this->getStorageFluxDeduction($shop, $flux_balance, $flux, QCOUND_CDN);
                        if ($funds_param)
                            $funds_params[] = $funds_param;
                        if (!$send_notice) {
                            $flux_allow = $flux_balance - $flux;
                            $send_notice = ($shop->flux_free > 0 && $flux_allow / $shop->flux_free <= 0.1);
                        }
                    }
                    //基础版才处理扣费和通知
                    if ($shop->version == VERSION_BASIC && $send_notice) {
                        $shop_notice_ids[] = $k;
                    }
                    if ($shop->version == VERSION_BASIC && $shop->balance >= 0 && $shop->balance < 500) {
                        $shop_funds_not_enough_notice_ids[] = $k;
                    }
                }
                //记录流量存储统计
                if (isset($storage_flux_params) && count($storage_flux_params) > 0) {
                    ShopStorageFlux::insert($storage_flux_params);
                }
                //扣费
                if (isset($funds_params) && count($funds_params) > 0) {
                    ShopFunds::insert($funds_params);
                }
                //发送通知
                if (isset($shop_notice_ids) && count($shop_notice_ids) > 0) {
                    SystemNotice::sendShopsSystemNotice($shop_notice_ids, 'notice.title.score.not_enough_storage_flux',
                        'notice.content.score.not_enough_storage_flux');
                }
                if (isset($shop_funds_not_enough_notice_ids) && count($shop_funds_not_enough_notice_ids) > 0) {
                    SystemNotice::sendShopsSystemNotice($shop_funds_not_enough_notice_ids, 'notice.title.score.not_enough',
                        'notice.content.score.not_enough');
                }
            }
            if (count($shops) < $count)
                break;
            $page++;
        }
        Redis::setex($key.":end:", $time, date('Y-m-d H:i:s'));
    }

    private function getStorageFluxCharge($shop_ids){
        if($shop_ids && count($shop_ids) >0){
            $timesTamp = time();
            $data = ['app_id' => $shop_ids, 'start_time' => $this->date, 'end_time' => $this->date];
            $client = hg_verify_signature($data, $timesTamp, $this->app_id, $this->app_secret);
            $options = ['query' => $data];
            try {
                $res = $client->request('GET', $this->url, $options);
            }catch (\Exception $exception){
                event(new CurlLogsEvent($exception->getMessage(),$client, $this->url));
            }
            if($res->getStatusCode() != 200) {
                //TODO 失败重试
                echo "服务商城报错了啊!!!!!\n";
                return false;
            }
            $data = json_decode($res->getBody()->getContents());
            if ($data && $data->error_code == 0) {
                $result = $data->result;
                $map = [];
                foreach ($shop_ids as $shop_id) {
                    if (isset($result->{$shop_id})) {
                        $charge_logs = $result->{$shop_id};
                        $storage = 0;
                        $flux = 0;
                        foreach ($charge_logs as $charge_log) {
                            if ($charge_log->cloud_type == QCOUND_COS)
                                $storage += $charge_log->value / 1024;
                            else if ($charge_log->cloud_type == QCOUND_CDN)
                                $flux += $charge_log->value / 1024;
                        }
                        $map[$shop_id] = [
                            'storage' => $storage,
                            'flux' => $flux,
                        ];
                    }
                }
                return $map;
            } else {
                echo "服务商城报错了啊....\n";
                //TODO 失败重试
                return false;
            }
        }
        return false;
    }

    private function getStorageFluxParams($shop_id, $value, $type)
    {
        $datetime = date('Y-m-d H:i:s');
        if ($value > 0) {
            return [
                'shop_id' => $shop_id,
                'value' => $value,
                'type' => $type,
                'date' => $this->date,
                'source'=> $this->env,
                'created_at'=>$datetime,
                'updated_at'=>$datetime,
            ];

        }
    }

    private function getStorageFluxDeduction($shop, $balance, $value, $type)
    {
        //基础版才需要结算使用超出余额部分
        if($value > $balance && $shop->version == VERSION_BASIC) {
            $datetime = date('Y-m-d H:i:s');
            $shop_id = $shop->hashid;
            //使用超出余额部分
            $exceed = $value - $balance;
            //KB转成GB
            $exceed = $exceed / 1048576;
            //不足0.0001GB不收取费用
            if ($exceed >= 0.0001) {
                $unit_price = 0;
                $product_type = '';
                $product_name = '';
                if($type == QCOUND_COS){
                    $unit_price = DEFAULT_QCLOUD_COS_UNIT_PRICE;
                    $product_type = QCOUND_COS;
                    $product_name = QCOUND_COS_NAME;
                } else if($type == QCOUND_CDN){
                    $unit_price = DEFAULT_QCLOUD_CDN_UNIT_PRICE;
                    $product_type = QCOUND_CDN;
                    $product_name = QCOUND_CDN_NAME;
                }
                $price = ceil($unit_price * $exceed);
                $order_id = time() . mt_rand(111111, 999999);
                $shop->balance = ($shop->balance - $price);
                $param = [
                    'shop_id'     => $shop_id,
                    'transaction_no'    => $order_id,
                    'product_type'  => $product_type,
                    'product_name'  => $product_name,
                    'type' => FUNDS_EXPAND,
                    'unit_price'  => -$unit_price,
                    'quantity'       => $exceed,
                    'total_price'    => -$price,
                    'amount'    => -$price,
                    'balance' => $shop->balance,
                    'date' => $this->now_date,
                    'created_at'=>$datetime,
                    'updated_at'=>$datetime,
                ];
                return $param;
            }
        }
    }
}