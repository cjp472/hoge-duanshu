<?php
namespace App\Http\Controllers\Manage\Member;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Member;
use App\Models\Manage\Order;
use App\Models\Manage\Payment;
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
     * 用户分布
     */
    public function memberDistribute()
    {
        $sexAndLangDistribute = $this->sexAndLangDistribute();
        return $this->output($sexAndLangDistribute);
    }

    /**
     * 性别分布,语言分布
     * @return mixed
     */
    private function sexAndLangDistribute(){
        $sexInfo = Member::selectRaw('count(id) as count')->addSelect('sex')->groupBy('sex')->pluck('count','sex');
        $sex = [ 0=>'other',1=>'man',2=>'woman'];
        $langInfo = Member::selectRaw('count(id) as count')->addSelect('language')->groupBy('language')->pluck('count','language');
        $areaInfo = Member::selectRaw('count(id) as value')->addSelect('province as name')->groupBy('province')->get();
        $sourceInfo = Member::selectRaw('count(id) as count')->addSelect('source')->groupBy('source')->pluck('count','source');
        $data['sex']['values'] = $this->getSexNumber($sexInfo,$sex);
        $data['lang']['values'] = $this->getLangNumber($langInfo);
        $data['area']['values'] = $areaInfo;
        $data['source']['values'] = $this->getSourceNumber($sourceInfo);
        return $data;
    }

    /**
     * 处理来源分布
     * @param $data
     * @return array
     */
    private function getSourceNumber($data)
    {
        $return = [];
        $source = [ 0=>'wechat',1=>'app',2=>'applet',3=>'mobile',4=>'inner',5=>'other'];
        foreach ($source as $key => $value) {
            $return[$value]['name'] = $value;
            $return[$value]['data'][] = isset($data[$value]) ? $data[$value] : 0;;
        }
        return $return;
    }

    /**
     * 处理性别分布
     * @param $data
     * @param $sex
     * @return array
     */
    private function getSexNumber($data,$sex){
        $return = [];
        if($sex){
            foreach($sex as $key=>$value){
                $return[$value]['name'] = $value ? : 'other';
                $return[$value]['data'][] = isset($data[$key]) ? $data[$key] : 0;
            }
        }
        return $return ? : [];
    }

    /**
     * 处理语言分布
     * @param $data
     * @return array
     */
    private function getLangNumber($data){
        $return = [];
        $lang = ['zh_CN','en','unknown','zh_trad'];
        if($lang){
            foreach($lang as $value){
                $return[$value]['name'] = $value ? : '未知';
                $return[$value]['data'][] = isset($data[$value]) ? $data[$value] : 0;
            }
        }
        return $return ? : [];
    }

    private function getAreaNumber($data){
        $return = [];
        if($data){
            foreach($data as $key=>$value){
                $return[$key]['name'] = $key ? : 'unknown';
                $return[$key]['data'][] = $value;
            }
        }
        return $return ? : [];

    }

    /**
     * 会员基本数据统计
     */
    public function memberTotal()
    {
        $consume_total = Member::where('amount','>',0)->count('id');
        $today_consume_total = Member::whereBetween('create_time',[strtotime(date('Y-m-d 00:00:00',time())),time()])->where('amount','>',0)->count('id');
        $today = Member::whereBetween('create_time',[strtotime(date('Y-m-d 00:00:00',time())),time()])->count('id');
        $total = Member::count('id');
        return $this->output([
            'today_consume' => $today_consume_total,
            'today'    => $today,
            'consume' => $consume_total,
            'total' => $total,
        ]);
    }

    /**
     * 会员增长趋势统计
     */
//    public function memberChart()
//    {
//        $this->validateWith([
//            'type'          => 'numeric',
//        ]);
//        $info = $this->get_type_data();
//        return $this->output($info);
//    }

    public function memberDataChart()
    {
        $this->validateWith([
            'type'          => 'numeric|in:0,1,2,3,4,5',
        ]);
        $info = $this->getTypeData(request('type'));
        return $this->output($info);
    }

    protected function getTypeData($type)
    {
        switch(request('type')){
            case 5: //自定义
                $start = request('start');
                $end = request('end');
                if($end-$start > 30*86400){
                    $this->errorWithText('time_over','时间跨度太大,最多支持30天');
                }
                $ret = $this->getMemberData('Ymd',$start,$end);
                break;
            case 4: //按年
                $start = request('date');
                $end = strtotime("+1 year",$start)-1;
                $ret = $this->getMemberData('Ym',$start,$end);
                break;
            case 3: //自然月
                $start = request('date');
                $end = strtotime("+1 month",$start)-1;
                $ret = $this->getMemberData('Ymd',$start,$end);
                break;
            case 2: //自然周
                $start = request('date');
                $end = strtotime("+1 week",$start)-1;
                $ret = $this->getMemberData('Ymd', $start, $end);
                break;
            case 1: //自然天
                $start = request('date');
                $end = strtotime("+1 day",$start)-1;
                $ret = $this->getRealTime($start , $end);
                break;
            default: //实时
                $ret = $this->getRealTime(strtotime(date('Y-m-d 00:00:00')), time());
                break;
        }
        return $ret;
    }

    protected function getMemberData($type,$start,$end)
    {
        $result = $temp = [];
        $data = CronStatistics::select('member','paid_member','create_time','year','month','day')->where('shop_id','total')->whereBetween('create_time',[$start,$end])->orderBy('create_time')->get();
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

        if(!$data->isEmpty()){
            foreach($data as $value){
                $hour = date($type, $value->create_time);
                if(0 && isset($temp[$hour])){
                    $temp[$hour] = [
                        'consume' => $value->paid_member+$temp[$hour]['consume'],
                        'member' => $value->member+$temp[$hour]['member'],
                    ];
                }else{
                    $temp[$hour] = [
                        'consume' => $value->paid_member,
                        'member' => $value->member,
                    ];
                }
            }
            for($k = $start;$k < strtotime(date('Y-m-d',$end)); $k = strtotime($plus,$k)){
                $date2 = date($type,$k);
                $result['consume']['keys'][] = date($formate,$k);
                $result['consume']['values'][] = isset($temp[$date2]) ? $temp[$date2]['consume'] : 0 ;

                $result['member']['keys'][] = date($formate,$k);
                $result['member']['values'][] = isset($temp[$date2]) ? $temp[$date2]['member'] : 0 ;;
            }
        }
        return $result;
    }

    /**
     * 获取不同时间段数据
     * @return array
     */
//    private function get_type_data(){
//        switch(request('type')){
//            case 1: //按天
//                $key = 'manage:member:day';
//                if(!request('cache') && Redis::exists($key)){
//                    $ret = json_decode(Redis::get($key),1);
//                }else{
//                    $ret = $this->getDayData();
//                    Redis::setex($key,4*3600,json_encode($ret));
//                }
//                break;
//            case 2: //按月
//                $key = 'manage:member:month';
//                if(!request('cache') && Redis::exists($key)){
//                    $ret = json_decode(Redis::get($key),1);
//                }else{
//                    $ret = $this->getMonthData();
//                    Redis::setex($key,0.5*86400,json_encode($ret));
//                }
//                break;
//            case 3: //按月
//                $key = 'manage:member:week';
//                if(!request('cache') && Redis::exists($key)){
//                    $ret = json_decode(Redis::get($key),1);
//                }else{
//                    $ret = $this->getWeekData();
//                    Redis::setex($key,0.5*86400,json_encode($ret));
//                }
//                break;
//            default: //实时
//                $key = 'manage:member:hour';
//                if(!request('cache') && Redis::exists($key)){
//                    $ret = json_decode(Redis::get($key),1);
//                }else{
//                    $ret = $this->getRealTime();
//                    Redis::setex($key,1800,json_encode($ret));
//                }
//                break;
//        }
//        return $ret;
//    }
    /**
     * 按天
     * @return array
     */
    private function getDayData(){
        return $this->getDayMonthData('Ymd', strtotime("-60 days"));
    }

    /**
     * 按月
     * @return array
     */
    private function getMonthData(){
        return $this->getDayMonthData('Ym', strtotime(date('2017-05-01 00:00:00')));
    }

    /**
     * 按周
     * @return array
     */
    private function getWeekData(){
        return $this->getDayMonthData('YW', strtotime(date('Y-01-01 00:00:00')));
    }


    /**
     * 获取实时数据
     */
    private function getRealTime($start_time,$end){
        $member = $this->getMemberNumber('YmdH',$start_time,$end);
        $consume_member = $this->getConsumeMemberNumber('YmdH',$start_time,$end);
        $active = $this->getActiveMember('YmdH',$start_time,$end);
        $active_consume = $this->getActiveConsumeMember('YmdH',$start_time,$end);
        return [
            'member'    => $member,
            'consume'   => $consume_member,
//            'active'    => $active,
//            'active_consume'    => $active_consume,
        ];
    }

    private function getDayMonthData($type,$begin){
        $member = $this->getMemberNumber($type,$begin,time());
        $consume_member = $this->getConsumeMemberNumber($type,$begin,time());
        $active = $this->getActiveMember($type,$begin,time());
        $active_consume = $this->getActiveConsumeMember($type,$begin,time());

        return [
            'member'    => $member,
            'consume'   => $consume_member,
//            'active'    => $active,
//            'active_consume'    => $active_consume,
        ];
    }

    /**
     * 获取会员数
     */
    private function getMemberNumber($type,$start,$end){
        switch ($type){
            case 'Ym':
                $formate = '%Y%m';break;
            case 'Ymd':
                $formate = '%Y%m%d';break;
            case 'YmdH':
                $formate = '%Y%m%d%H';break;
            case 'YW':
                $formate = '%Y%u';break;
        }
        $info = Member::whereBetween('create_time',[$start,$end])
                ->selectRaw("FROM_UNIXTIME(create_time,'$formate') as day,count(id) as total")
                ->groupBy('day')
                ->pluck('total','day');
        return hg_analysis_formate($info,$type,$start,$end);
    }

    /**
     * 新增付费会员
     */
    private function getConsumeMemberNumber($type,$start,$end){
        switch ($type){
            case 'Ym':
                $formate = '%Y%m';break;
            case 'Ymd':
                $formate = '%Y%m%d';break;
            case 'YmdH':
                $formate = '%Y%m%d%H';break;
            case 'YW':
                $formate = '%Y%u';break;
        }

        $info = Order::where('order.pay_status','>',0)
            ->whereBetween('pay_time',[$start,$end])
            ->leftJoin('member','member.uid','order.user_id')
            ->selectRaw("FROM_UNIXTIME(pay_time,'$formate') as day,count(*) as total")
            ->groupBy('day')
            ->pluck('total','day');
        return hg_analysis_formate($info,$type,$start,$end);
    }

    /**
     * 活跃会员
     */
    private function getActiveMember($type,$start,$end){
        return [];
        return $this->getMemberNumber($type,$start,$end);
    }

    /**
     * 付费活跃会员
     */
    private function getActiveConsumeMember($type,$start,$end){
        return [];
        return $this->getConsumeMemberNumber($type,$start,$end);
    }

    public function memberTop()
    {
        $this->validateWith([
            'time'      => 'numeric|in:1,2,3',   //1天 2周 3月
            'condition' => 'required|alpha_dash|in:share,consum' //share分享 consum额度
        ]);
        $data = $this->timeChoice(request('time'),request('condition'));
        arsort($data);
        return $this->output($data);
    }


    private function timeChoice($time,$condition)
    {
        switch ($time) {
            case 1:
                $current = mktime(0,0,0,date('m'),date('d'),date('Y'));
                return $this->getMember($current,$condition);
                break;
            case 2:
                $current = mktime(0,0,0,date('m'),date('d')-7,date('Y'));
                return $this->getMember($current,$condition);
                break;
            case 3:
                $current = mktime(0,0,0,date('m')-1,date('d'),date('Y'));
                return $this->getMember($current,$condition);
                break;
            default:
                $current = 0;
                return $this->getMember($current,$condition);
                break;
        }
    }

    private function getMember($start,$condition)
    {
        if ($condition == 'consum') {
            $data = Payment::whereBetween('order_time',[$start,time()])->groupBy('user_id')->select(DB::raw('user_id,sum(price) as consum'))->limit(100)->pluck('consum','user_id')->toArray();
        } elseif ($condition == 'share') {

        }
        return $data;
    }
}