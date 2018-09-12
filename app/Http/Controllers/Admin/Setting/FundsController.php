<?php
/**
 * 店铺信息查看
 */

namespace App\Http\Controllers\Admin\Setting;

use App\Events\CurlLogsEvent;
use App\Events\SystemEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Models\FightGroupActivity;
use App\Models\Member;
use App\Models\Postage;
use App\Models\Shop;
use App\Models\ShopColor;
use App\Models\ShopFunds;
use App\Models\ShopInfo;
use App\Models\ShopFlow;
use App\Models\ShopScore;
use App\Models\ShopStorageFlux;
use App\Models\UserButtonClicks;
use App\Models\UserShop;
use App\Models\VersionExpire;
use App\Models\WebsiteSituation;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use qcloudcos\Cosapi;
use Maatwebsite\Excel\Facades\Excel;


class FundsController extends BaseController
{
    /**
     * 指定时间或者6个月之内的日期账单列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function billSummaryList()
    {
        $count = request('count') ?: 10;
        $limit_start_time = date('Y-m-d', strtotime('-6 months'));  //6个月前
        $start_time = request('start_time');
        $start_time = $start_time || $start_time > $limit_start_time ? $start_time : $limit_start_time;
        $end_time = request('end_time');
        $type = request('type');
        $shop_id = $this->shop['id'];
        $where = ['shop_id' => $shop_id, 'status' => 0];
        $sql = ShopFunds::where($where)->selectRaw("date, sum(amount) as total, type")->groupBy('date')->groupBy('type')->orderBy('date', 'desc');
        $sql->where('date', '>=', $start_time);
        if ($end_time) {
            $end_time = date("Y-m-d", strtotime($end_time) + 24 * 60 * 60);
            $sql->where('created_at', '<', $end_time);
        }
        if ($type) {
            $sql->where('type', $type);
        }
        $data = $sql->paginate($count);
        return $this->output($this->listToPage($data));
    }

    /**
     * 某一天的账单
     */
    public function dateBillDetail()
    {
        $date = request('date');
        $type = request('type');
        if (!$date) {
            $this->error('funds.param_invalid', ['name' => 'date']);
        }
        if (!$type) {
            $this->error('funds.param_invalid', ['name' => 'type']);
        }
        $shop_id = $this->shop['id'];
        $where = ['shop_id' => $shop_id, 'status' => 0, 'date' => $date, 'type' => $type];
        $sql = ShopFunds::where($where)->selectRaw("date, sum(amount) as total, type")->groupBy('date');
        $data = $sql->first();
        $sql = ShopFunds::where($where)->selectRaw("balance")->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        $balance_data = $sql->first();
        $data->balance = $balance_data->balance;
        return $this->output($data);
    }

    /**
     * 某一天的账单详情列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function dateBillList()
    {
        $count = request('count') ?: 10;
        $date = request('date');
        $type = request('type');
        if (!$date) {
            $this->error('funds.param_invalid', ['name' => 'date']);
        }
        if (!$type) {
            $this->error('funds.param_invalid', ['name' => 'type']);
        }
        $shop_id = $this->shop['id'];
        $where = ['shop_id' => $shop_id, 'status' => 0, 'date' => $date, 'type' => $type];
        $data = ShopFunds::where($where)->orderBy('created_at', 'desc')->orderBy('id', 'desc')
            ->paginate($count);

        return $this->output($this->listToPage($data));
    }

    /**
     * 短书币余额
     * @return \Illuminate\Http\JsonResponse
     */
    public function balance()
    {
        $shop_id = $this->shop['id'];
        $shop = Shop::where(['hashid' => $shop_id])->first();
        $now = time();
        $storageFluxBalance = $shop->getStorageFluxBalance($now);
        //开始时间
        $start_time = $storageFluxBalance['start_time'];
        //结束时间
        $end_time = $storageFluxBalance['end_time'];
        //免费存储空间
        $shop_storage_default = $storageFluxBalance['storage'];
        //免费流量
        $shop_flux_default = $storageFluxBalance['flow'];
        //存储空间余额
        $storage_allow = $storageFluxBalance['storage_allow'];
        $storage_percent = $storageFluxBalance['storage_percent'];
        //流量余额
        $flux_allow = $storageFluxBalance['flux_allow'];
        $flux_percent = $storageFluxBalance['flux_percent'];
        
        $amount = ShopFunds::getBalance($shop_id);
        $data = [
            'start_time' => date('m月d日', strtotime($start_time)),
            'end_time' => date('m月d日', strtotime($end_time)),
            'balance' => $amount,
            'storage' => number_format($shop_storage_default / (1024 * 1024), 4, '.', ''),
            'flux' => number_format($shop_flux_default / (1024 * 1024), 4, '.', ''),
            'storage_balance' => number_format($storage_allow / (1024 * 1024), 4, '.', ''),
            'storage_percent' => number_format($storage_percent, 2, '.', ''),
            'flux_balance' => number_format($flux_allow / (1024 * 1024), 4, '.', ''),
            'flux_percent' => number_format($flux_percent, 2, '.', ''),
        ];
        return $this->output($data);
    }

    /**
     * 账单导出
     */
    public function billExport()
    {
        $limit_start_time = date('Y-m-d', strtotime('-6 months'));  //6个月前
        $start_time = request('start_time');
        $start_time = $start_time || $start_time > $limit_start_time ? $start_time : $limit_start_time;
        $end_time = request('end_time');
        $type = request('type');
        $shop_id = $this->shop['id'];
        $where = ['shop_id' => $shop_id, 'status' => 0];
        $sql = ShopFunds::where($where)->selectRaw("date, sum(amount) as total, type")->groupBy('date')->groupBy('type')->orderBy('date', 'desc');
        $sql->where('date', '>=', $start_time);
        if ($end_time) {
            $end_time = date("Y-m-d", strtotime($end_time) + 24 * 60 * 60);
            $sql->where('created_at', '<', $end_time);
        }
        if ($type) {
            $sql->where('type', $type);
        }
        $query_set = $sql->get();
        if ($query_set) {
            $title = ['结算时间', '结算类型', '结算费用'];
            $data = [];
            foreach ($query_set as $q) {
                $item = [
                    'date' => $q->date,
                    'type' => $q->type == 'expand' ? '消费' : '充值',
                    'total' => number_format($q->total / 100, 2),
                ];
                array_push($data, $item);
            }
            array_unshift($data, $title);
            Excel::create('短书币账单', function ($excel) use ($data) {
                $excel->sheet('funds', function ($sheet) use ($data) {
                    $sheet->fromArray($data, null, 'A1', false, false);
                });
            })->export('xlsx');
        } else {
            return $this->error('null_data');
        }
    }

    /**
     * 当前月份存储导出明细
     */
    public function storageExport(){
        $shop_id = $this->shop['id'];
        return $this->storageFluxExport($shop_id, QCOUND_COS, '存储空间使用情况表', 'storage');
    }

    /**
     *
     */
    public function fluxExport(){
        $shop_id = $this->shop['id'];
        return $this->storageFluxExport($shop_id, QCOUND_CDN, '流量使用情况表', 'flux');
    }

    /**
     * @param $shop_id
     * @param $type
     * @param $name
     * @param $slug
     */
    private function storageFluxExport($shop_id, $type, $name, $slug){
        $shop = Shop::where(['hashid' => $shop_id])->first();
        $now = time();
        $query_set = $shop->getStorageFluxList($now, $type);
        if ($query_set) {
            $title = ['结算周期', '结算时间', '使用情况(GB)'];
            $data = [];
            foreach ($query_set as $q) {
                $date = date('Y-m-d', strtotime('+ 1days', strtotime($q->date)));
                $time = $q->date.' 00:00-23:59';
                $item = [
                    'time' => $time,
                    'date' => $date,
                    'total' => number_format($q->value / (1024 * 1024), 4),
                ];
                array_push($data, $item);
            }
            array_unshift($data, $title);
            Excel::create($name, function ($excel) use ($data, $slug) {
                $excel->sheet($slug, function ($sheet) use ($data) {
                    $sheet->fromArray($data, null, 'A1', false, false);
                });
            })->export('xlsx');
        } else {
            return $this->error('null_data');
        }
    }
}