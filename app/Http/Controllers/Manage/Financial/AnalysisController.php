<?php
/**
 * Created by Guhao.
 * User: wzs
 * Date: 17/4/26
 * Time: 上午9:08
 */

namespace App\Http\Controllers\Manage\Financial;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\CronStatistics;

class AnalysisController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function financialTotal()
    {
        $todayFinancial = Order::where('pay_status', 1)
            ->whereBetween('pay_time', [strtotime(date('Y-m-d 00:00:00', time())), time()])
            ->sum('price');
        $yesterdayIncome = Order::where('pay_status', 1)
            ->whereBetween('pay_time', [
                mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')),
                mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1
            ])
            ->sum('price');
        $totalIncome = Order::where('pay_status', 1)->sum('price');
        return $this->output([
            'todayIncome'     => $todayFinancial,
            'yesterdayIncome' => $yesterdayIncome,
            'totalIncome'     => $totalIncome,
        ]);
    }

    /**
     * 收入增长统计
     */
//    public function financialChart()
//    {
//        $this->validateWith([
//            'type'   => 'numeric',
//            'source' => 'alpha_dash'
//        ]);
//        $info = $this->getFinancialTypeData();
//        return $this->output($info);
//    }

    public function financialDataChart()
    {
        $this->validateWith([
            'type'   => 'numeric',
        ]);
        $info = $this->getFinancialDataType(request('type'));
        return $this->output($info);
    }

    protected function getFinancialDataType($type)
    {
        switch ($type) {
            case 5: //自定义
                $start = request('start');
                $end = request('end');
                if($end-$start > 30*86400){
                    $this->errorWithText('time_over','时间跨度太大,最多支持30天');
                }
                $ret = $this->getFinancialData('Ymd',$start,$end);
                break;
            case 4: //按年
                $start = request('date');
                $end = strtotime("+1 year",$start)-1;
                $ret = $this->getFinancialData('Ym',$start,$end);
                break;
            case 3: //自然月
                $start = request('date');
                $end = strtotime("+1 month",$start)-1;
                $ret = $this->getFinancialData('Ymd',$start,$end);
                break;
            case 2: //自然周
                $start = request('date');
                $end = strtotime("+1 week",$start)-1;
                $ret = $this->getFinancialData('Ymd', $start, $end);
                break;
            case 1: //自然天
                $start = request('date');
                $end = strtotime("+1 day",$start)-1;
                $ret = $this->getFinancialRealTime($start , $end);
                break;
            default: //实时
                $ret = $this->getFinancialRealTime(strtotime(date('Y-m-d 00:00:00')), time());
                break;
        }
        return $ret;
    }

    protected function getFinancialData($type,$start,$end)
    {
        $data = CronStatistics::select('yesterday_income','create_time','year','month','day')->where('shop_id','total')->whereBetween('create_time',[$start,$end])->orderBy('create_time')->get();
        if($type == 'Ym') {
            $plus = "+1 month";
            $formate = 'Y/m';
        }elseif($type == 'Ymd'){
            $plus = "+1 day";
            $formate = 'm/d';
        }elseif($type == 'YW'){
            $plus = "+1 week";
            $formate = 'Y第W周';
        }
        $result = $temp = [];
        if(!$data->isEmpty()){
            foreach($data as $value){
                $hour = date($type, $value->create_time);
                if(0 && isset($temp[$hour])){
                    $temp[$hour] = [
                        'income' => $value->yesterday_income+$temp[$hour]['income'],
                    ];
                }else{
                    $temp[$hour] = [
                        'income' => $value->yesterday_income,
                    ];
                }
            }
            for($k = $start;$k < $end; $k = strtotime($plus,$k)){
                $date2 = date($type,$k);
                $result['keys'][] = date($formate,$k);
                $result['values'][] = isset($temp[$date2]) ? $temp[$date2]['income'] : 0 ;
            }
        }
        return $result;
    }

    /**
     * 个类型收入占比
     */
    public function financialPercent()
    {
        $this->validateWith([
            'source'   => 'alpha_dash'
        ]);
        $financial = $this->allFinancial();
        $keyArray = ['article', 'video', 'audio', 'column', 'live','course'];
        $data = [];
        if ($keyArray) {
            $total = $financial->sum();
            foreach ($keyArray as $key => $val) {
                $amount = isset($financial[$val]) ? $financial[$val] : 0;
                $data[] = [
                    'name'    => $val,
                    'value'   => $amount,
                    'percent' => $amount ? round($amount / $total * 100, 2) : 0,
                ];
            }
        }
        return $this->output($data);
    }

    /**
     * 优质内容-top10
     * @return mixed
     */
    public function highContent(){
        $result = Order::where('content.up_time','<',time())
            ->where('pay_status',1)
            ->leftJoin('content','order.content_id','content.hashid')
            ->groupBy('content_id')
            ->orderBy('totalPrice','desc')
            ->limit(10)
            ->select(DB::raw('sum(hg_order.price) as totalPrice'),'content_id','content.title','content.type','content.up_time')
            ->get();
        foreach ($result as $key => $item){
            $item->sort = $key+1;
            $item->dates = ceil((time() - $item->up_time)/86400);
            $item->up_time = date('Y-m-d H:i:s',$item->up_time);
        }
        return $this->output(['data' => $result]);
    }

    /**
     * 总收入
     * @return mixed
     */
    private function allFinancial()
    {
        $result = Order::where('pay_status', 1)
            ->groupBy('content_type')
            ->select(DB::raw('sum(price) as allIncome'), 'content_type as type');
        request('source') && $result->where('source',request('source'));
        $result = $result->pluck('allIncome', 'type');
        return $result;

    }

    /**
     * 获取收入不同条件下的统计数据
     * @return mixed
     */
    private function getFinancialTypeData()
    {
        switch (request('type')) {
            case 1: //按天
                $key = 'manage:finance:day';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getFinancialDayData();
                    Redis::setex($key,4*3600,json_encode($ret));
                }
                break;
            case 2: //按月
                $key = 'manage:finance:month';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getFinancialMonthData();
                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            case 3: //按周
                $key = 'manage:finance:week';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getFinancialWeekData();
                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            default: //实时
                $key = 'manage:finance:hour';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getFinancialRealTime();
                    Redis::setex($key,1800,json_encode($ret));
                }
                break;
        }
        return $ret;
    }

    /**
     * 按天
     * @return array
     */
    private function getFinancialDayData()
    {
        $start = strtotime("-60 days");
        $end = time();
        $type = 'Ymd';
        $info = $this->getFinancial($start,$end,$type);
        return $info ?: [];
    }

    /**
     * 按月
     * @return array
     */
    private function getFinancialMonthData()
    {
        $type = 'Ym';
        $start = strtotime(date('2017-05-01 00:00:00'));
        $end = time();
        $info = $this->getFinancial($start,$end,$type);
        return $info ?: [];
    }
    /**
     * 按周
     * @return array
     */
    private function getFinancialWeekData()
    {
        $type = 'YW';
        $start = strtotime(date('Y-01-01 00:00:00'));
        $end = time();
        $info = $this->getFinancial($start,$end,$type);
        return $info ?: [];
    }

    private function getFinancialRealTime($start,$end)
    {
        $type = 'YmdH';
        $info = $this->getFinancial($start,$end,$type);
        return $info ?: [];
    }


    /**
     * 获取收入数据
     * @param $start
     * @param $end
     * @return mixed
     */
    private function getFinancial($start, $end,$type)
    {
        $formate = hg_analysis_sql_formate($type);
        $order = Order::where('pay_status', 1)
            ->whereBetween('pay_time', [$start, $end])
            ->orderBy('pay_time', 'asc')
            ->selectRaw("FROM_UNIXTIME(pay_time,'$formate') as day,sum(price) as total")
            ->groupBy('day');
        request('source') && $order->where('source',request('source'));
        $info = $order->pluck('total','day');
        return hg_analysis_formate($info,$type,$start,$end);

    }
}