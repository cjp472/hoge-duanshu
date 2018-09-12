<?php
/**
 * Created by Guhao.
 * User: wzs
 * Date: 17/4/26
 * Time: 上午9:03
 */
namespace App\Http\Controllers\Manage\Content;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\ClassContent;
use App\Models\Course;
use App\Models\Manage\Content;
use App\Models\Manage\Order;
use App\Models\Manage\Views;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\CronStatistics;


class AnalysisController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 内容分析
     * @return mixed
     */
    public function contentTotal()
    {

        $start_time = strtotime(date('Y-m-d 00:00:00', time()));
        $todayOrder = $this->todayOrder($start_time);  //今日订单
        $allOrder = $this->allOrder();      //总订单
        $todayIncome = $this->todayIncome($start_time);  //今日新增收入
        $allIncome = $this->allIncome();                 //总收入
        $todayView = $this->todayView($start_time);
        $allView = array_merge($this->allView(), $this->allColumn(),$this->allCourse());   //总浏览量
        $keyArray = ['article', 'audio', 'video', 'live', 'column','course'];
        $data = [];
        foreach ($keyArray as $key => $item) {
            $torder = isset($todayOrder[$item]) ? $todayOrder[$item] : 0;
            $tincome = isset($todayIncome[$item]) ? $todayIncome[$item] : 0;
            $aorder = isset($allOrder[$item]) ? $allOrder[$item] : 0;
            $tview = isset($todayView[$item]) ? $todayView[$item] : 0;
            $aview = isset($allView[$item]) ? $allView[$item] : 0;
            $aincome = isset($allIncome[$item]) ? $allIncome[$item] : 0;
            $data[] = [
                'type'        => $item,
                'todayOrder'  => $torder,
                'todayView'   => $tview,
                'todayIncome' => $tincome,
                'allOrder'    => $aorder,
                'allView'     => $aview,
                'allIncome'   => $aincome
            ];
        }
        return $this->output($data);
    }

    /**
     * 今日内容-折线图分析
     */
    public function contentChart(){
        $this->validateWith([
            'type'    => 'alpha-dash',    //内容分类
            'time'    => 'numeric'
        ]);
        $info = $this->getTimeType();
        return $this->output($info);
    }

    public function contentDataChart()
    {
        $this->validateWith([
            'time'    => 'numeric'
        ]);
        $info = $this->getTimeDataType(request('time'));
        return $this->output($info);
    }

    protected function getTimeDataType($time)
    {
        switch ($time) {
            case 5: //自定义
                $start = request('start');
                $end = request('end');
                if($end-$start > 30*86400){
                    $this->errorWithText('time_over','时间跨度太大,最多支持30天');
                }
                $ret = $this->getIncreaseData('Ymd',$start,$end);
                break;
            case 4: //按年
                $start = request('date');
                $end = strtotime("+1 year",$start)-1;
                $ret = $this->getIncreaseData('Ym',$start,$end);
                break;
            case 3: //自然月
                $start = request('date');
                $end = strtotime("+1 month",$start)-1;
                $ret = $this->getIncreaseData('Ymd',$start,$end);
                break;
            case 2: //自然周
                $start = request('date');
                $end = strtotime("+1 week",$start)-1;
                $ret = $this->getIncreaseData('Ymd', $start, $end);
                break;
            case 1: //自然天
                $start = request('date');
                $end = strtotime("+1 day",$start)-1;
                $ret = $this->getIncreaseRealTime($start , $end);
                break;
            default: //实时
                $ret = $this->getIncreaseRealTime(strtotime(date('Y-m-d 00:00:00')), time());
                break;
        }
        return $ret;
    }

    protected function getIncreaseData($type,$start,$end)
    {
        $data = CronStatistics::select('yesterday_income','click_num','order_num','create_time','year','month','day')->where('shop_id','total')->whereBetween('create_time',[$start,time()])->orderBy('create_time')->get();
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
                        'order' => $value->order_num+$temp[$hour]['order'],
                        'view' => $value->click_num+$temp[$hour]['view']
                    ];
                }else{
                    $temp[$hour] = [
                        'income' => $value->yesterday_income,
                        'order' => $value->order_num,
                        'view' => $value->click_num
                    ];
                }
            }
            for($k = $start;$k < strtotime(date('Y-m-d',$end)); $k = strtotime($plus,$k)){
                $date2 = date($type,$k);
                $result['income']['keys'][] = date($formate,$k);
                $result['income']['values'][] = isset($temp[$date2]) ? $temp[$date2]['income'] : 0 ;

                $result['order']['keys'][] = date($formate,$k);
                $result['order']['values'][] = isset($temp[$date2]) ? $temp[$date2]['order'] : 0 ;;

                $result['view']['keys'][] = date($formate,$k);
                $result['view']['values'][] = isset($temp[$date2]) ? $temp[$date2]['view'] : 0 ;;
            }
        }
        return $result;
    }

    /**
     *查询时间类型
     * @return array
     */
    private function getTimeType(){
        $type = request('type') ?: 'all';
        switch(request('time')){
            case 1: //按天
                $key = 'manage:content:day:'.$type;
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getIncreaseDayData();
                    Redis::setex($key,4*3600,json_encode($ret));
                }
                break;
            case 2: //按月
                $key = 'manage:content:month:'.$type;
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getIncreaseMonthData();
                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            case 3: //按周
                $key = 'manage:content:week:'.$type;
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getIncreaseWeekData();
                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            default: //实时
                $key = 'manage:content:hour:'.$type;
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getIncreaseRealTime();
                    Redis::setex($key,1800,json_encode($ret));
                }
                break;
        }
        return $ret;
    }

    /**
     * 按天
     */
    private function getIncreaseDayData(){
        $start = strtotime('-60 days');
        $end = time();
        return $this->getDayMonthDetail('Ymd',$start,$end);
    }

    /**
     * 按月
     */
    private function getIncreaseMonthData(){
        $start = strtotime(date('2017-05-01 00:00:00'));
        $end = time();
        return $this->getDayMonthDetail('Ym',$start,$end);
    }
    /**
     * 按周
     */
    private function getIncreaseWeekData(){
        $start = strtotime(date('Y-01-01 00:00:00'));
        $end = time();
        return $this->getDayMonthDetail('YW',$start,$end);
    }

    /**
     * 按时
     */
    private function getIncreaseRealTime($start,$end){
        $orderInfo = $this->increaseOrder('YmdH',$start,$end);
        $viewInfo = $this->increaseView('YmdH',$start,$end);
        $incomeInfo = $this->increaseIncome('YmdH',$start,$end);
        return $result = [
            'order'  => $orderInfo,
            'view'   => $viewInfo,
            'income' => $incomeInfo
        ];

    }

    /**
     * 天，月类型的
     * @param $type
     * @return array
     */
    private function getDayMonthDetail($type,$begin,$end){
        $orderInfo = $this->increaseOrder($type,$begin,$end); //订单
        $viewInfo = $this->increaseView($type,$begin,$end);   //浏览量
        $incomeInfo = $this->increaseIncome($type,$begin,$end); //收入
        return $result = [
            'order'  => $orderInfo,
            'view'   => $viewInfo,
            'income' => $incomeInfo
        ];
    }

    /**
     * 新增订单查询
     * @param $start
     * @param $end
     * @return array
     */
    private function increaseOrder($type,$start,$end){
        $formate = hg_analysis_sql_formate($type);
        $order = new Order();
        request('type') && $order = $order->where('content_type',request('type'));
        $res = $order
            ->whereBetween('order_time',[$start,$end])
            ->selectRaw("FROM_UNIXTIME(order_time,'$formate') as day,count(*) as total")
            ->groupBy('day')
            ->pluck('total','day');
        if($res){
            return hg_analysis_formate($res,$type,$start,$end);
        }
        return [];
    }

    /**
     * 新增阅读量查询
     * 当查询全部时，数量（不包括专栏）有些出入，以后优化
     * @param $type
     * @param $start
     * @param $end
     * @param $begin
     * @return array
     */
    private function increaseView($type,$start,$end){
        $formate = hg_analysis_sql_formate($type);
        $view = new Views();
        if(!request('type') || request('type') != 'column'){ //type不是专栏类型
            request('type') && $view = $view->where('content_type',request('type'));
            $res = $view
                ->whereBetween('view_time',[$start,$end])
                ->selectRaw("FROM_UNIXTIME(view_time,'$formate') as day,count(*) as total")
                ->groupBy('day')
                ->pluck('total','day');
        }else {
            $res = $view
                ->where('content_column','<>',0)
                ->whereBetween('view_time',[$start,$end])
                ->selectRaw("FROM_UNIXTIME(view_time,'$formate') as day,count(*) as total")
                ->groupBy('day')
                ->pluck('total','day');
        }
        if($res){
            return hg_analysis_formate($res,$type,$start,$end);
        }
        return [];
    }

    /**
     * 新增收入查询
     * @param $start
     * @param $end
     * @return array
     */
    private function increaseIncome($type,$start,$end){
        $formate = hg_analysis_sql_formate($type);
        $order = Order::where('pay_status',1);
        request('type') && $order = $order->where('content_type',request('type'));
        $res = $order
            ->whereBetween('pay_time',[$start,$end])
            ->selectRaw("FROM_UNIXTIME(pay_time,'$formate') as day,count(*) as total")
            ->groupBy('day')
            ->pluck('total','day');
        if($res){
            return hg_analysis_formate($res,$type,$start,$end);
        }
        return [];
    }

    /**
     * 今日订单数量(如果传入content_id则查单个内容)
     * @param $start_time
     * @return mixed
     */
    private function todayOrder($start_time)
    {
        $result = Order::whereBetween('order_time', [$start_time, time()])
            ->groupBy('content_type')
            ->select(DB::raw('count(id) as todayOrder'), 'content_type as type')
            ->pluck('todayOrder', 'type');
        return $result;
    }

    /**
     * 总订单数量
     * @return mixed
     */
    private function allOrder()
    {
        $result = Order::groupBy('content_type')
            ->select(DB::raw('count(id) as allOrder'), 'content_type as type')
            ->pluck('allOrder', 'type');
        return $result;
    }

    /**
     * 今日收入
     * @param $start_time
     * @return mixed
     */
    private function todayIncome($start_time)
    {
        $result = Order::where('pay_status', 1)
            ->whereBetween('pay_time', [$start_time, time()])
            ->groupBy('content_type')
            ->select(DB::raw('sum(price) as todayIncome'), 'content_type as type')
            ->pluck('todayIncome', 'type');
        return $result;

    }

    /**
     * 总收入
     * @return mixed
     */
    private function allIncome()
    {
        $result = Order::where('pay_status', 1)
            ->groupBy('content_type')
            ->select(DB::raw('sum(price) as allIncome'), 'content_type as type')
            ->pluck('allIncome', 'type');
        return $result;

    }

    /**
     * 内容类型的总阅读数目
     * @return mixed
     */
    private function allView()
    {
        $result = Content::groupBy('type')
            ->select(DB::raw('sum(view_count) as allView'), 'type')
            ->pluck('allView', 'type')->toArray();
        return $result;
    }

    /**
     * 专栏总阅读数
     * @return mixed
     */
    private function allColumn()
    {
        $result = Content::where('column_id', '<>', 0)
            ->select(DB::raw('sum(view_count) as allView,"column" as type'))
            ->pluck('allView', 'type')->toArray();
        return $result;
    }

    /**
     * 课程总阅读数据
     * @return int
     */
    private function allCourse(){
        $total = ClassContent::sum('view_count');
        return ['course'=>intval($total)];
    }

    private function todayView($start_time)
    {
        $result = Views::groupBy('content_type')
            ->whereBetween('view_time', [$start_time, time()])
            ->select(DB::raw('count(id) as todayView'), 'content_type as type')
            ->pluck('todayView', 'type');
        return $result;
    }

}