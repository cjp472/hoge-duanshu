<?php
/**
 * 店铺统计数据
 */
namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\BaseController;
use App\Models\AliveMessage;
use App\Models\CardRecord;
use App\Models\ClassContent;
use App\Models\Comment;
use App\Models\Content;
use App\Models\Course;
use App\Models\FeedBack;
use App\Models\Member;
use App\Models\Order;
use App\Models\Views;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use App\Events\CurlLogsEvent;
use Illuminate\Support\Facades\Redis;
use App\Models\CronStatistics;


class DashboardController extends BaseController
{
    /**
     * 用户基本数据统计
     */
    public function userTotal()
    {
        $consume_total = Member::where(['shop_id'=>$this->shop['id']])->where('amount','>',0)->count('id');
        $total = Member::where(['shop_id'=>$this->shop['id']])->count('id');
        $today_consume_total = Member::where(['shop_id'=>$this->shop['id']])->whereBetween('create_time',[strtotime(date('Y-m-d 00:00:00',time())),time()])->where('amount','>',0)->count('id');
        $today_total = Member::where(['shop_id'=>$this->shop['id']])->whereBetween('create_time',[strtotime(date('Y-m-d 00:00:00',time())),time()])->count('id');
        $active_consume = 0;
        $active_total = 0;
        return $this->output([
            'consume' => $consume_total,
            'today_consume' => $today_consume_total,
            'active_consume'    => $active_consume,
            'total'    => $total,
            'today_total'    => $today_total,
            'active_total'    => $active_total
        ]);

    }

    /**
     * 店铺统计接口
     */
    public function shopTotal(){
        $today_start = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $user = Member::where(['shop_id'=>$this->shop['id']])->whereBetween('create_time',[$today_start,time()])->where('amount','>',0)->count('id');
        $order = Order::where(['shop_id'=>$this->shop['id'],'pay_status'=>1])->whereBetween('order_time',[$today_start,time()])->count('id');
        $income = $this->getShopIncome();
        return $this->output([
            'income' => $income,
            'user'   => $user,
            'order'  => $order
        ]);
    }

    private function getShopIncome(){
        $param = $this->paramTotal();
        $client = $this->initClient(); //初始化 client
        $url = config('define.order_center.api.order_total');
        try {
            $res = $client->request('GET',  $url,['query'=>$param]);
        }catch (\Exception $exception){
            event(new CurlLogsEvent($exception->getMessage(),$client,$url));
            $this->error('error_order');
        }
        $result = $this->errorReturn($res); //出错处理和接收数据
        event(new CurlLogsEvent(json_encode($result),$client,$url));
        return $result['result']['seller_today_total']/100;
    }

    /**
     * 获取不同时间段数据
     * @return array
     */
    private function get_type_data(){
        switch(request('type')){
            case 1: //按天
                $ret = $this->getDayData();
                break;
            case 2: //按月
                $ret = $this->getMonthData();
                break;
            case -1: //自定义时间
                $ret = $this->getCustomTime();
                break;
            default: //实时
                $ret = $this->getRealTime();
                break;
        }
        return $ret;
    }

    /**
     * 自定义时间
     */
    private function getCustomTime(){
        $type = 'Ymd';
        $start_time = strtotime(request('start_time'));
        $end_time = strtotime(request('end_time'));
        $begin = request('start_time');
        $member = $this->getMemberNumber($type,$start_time,$end_time,0,$begin);
        $consume_member = $this->getConsumeMemberNumber($type,$start_time,$end_time,0,$begin);
        $active = $this->getActiveMember($type,$start_time,$end_time,0,$begin);
        $active_consume = $this->getActiveConsumeMember($type,$start_time,$end_time,0,$begin);

        return [
            'member'    => $member,
            'consume'   => $consume_member,
            'active'    => $active,
            'active_consume'    => $active_consume,
        ];

    }

    /**
     * 按天
     * @return array
     */
    private function getDayData(){
        $result = Cache::get('user:chart:day:'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $data = $this->getDayMonthData('Ymd', date('Y-m-01 00:00:00'));
            cache::put('user:chart:day:'.$this->shop['id'],json_encode($data),EXPIRE_DAY/60);
            return $data;
        }
    }

    /**
     * 按月
     * @return array
     */
    private function getMonthData(){
        $result = Cache::get('user:chart:month:'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $data = $this->getDayMonthData('Ym', date('2017-05-01 00:00:00'));
            Cache::put('user:chart:month:'.$this->shop['id'],json_encode($data),EXPIRE_MONTH/60);
            return $data;
        }
    }

    private function getDayMonthData($type,$begin){
        $member = $this->getMemberNumber($type,0,time(),0,$begin);
        $consume_member = $this->getConsumeMemberNumber($type,0,time(),0,$begin);
        $active = $this->getActiveMember($type,0,time(),0,$begin);
        $active_consume = $this->getActiveConsumeMember($type,0,time(),0,$begin);

        return [
            'member'    => $member,
            'consume'   => $consume_member,
            'active'    => $active,
            'active_consume'    => $active_consume,
        ];
    }

    private function getSearchData($ret){
//        $ret['search'] = [
//            0 => '按时',
//            1 => '按天',
//            2 => '按月',
//        ];
        return $ret;
    }

    /**
     * 获取用户数
     */
    private function getMemberNumber($type,$start,$end,$sign=0,$begin){

        $info = Member::where('shop_id',$this->shop['id'])
            ->whereBetween('create_time',[$start,$end])
            ->orderBy('create_time','asc')
            ->pluck('create_time');
        return !$sign ? $this->getDataKeyValue($info,$type,strtotime($begin),$end) : $this->getKeyValue($info,date('H'),0,':00');
    }

    /**
     * 新增付费会员
     */
    private function getConsumeMemberNumber($type,$start,$end,$sign=0,$begin){
        $info = Member::where('shop_id',$this->shop['id'])
            ->whereBetween('create_time',[$start,$end])
            ->where('amount','>',0)
            ->orderBy('create_time','asc')
            ->pluck('create_time');
        return !$sign ? $this->getDataKeyValue($info,$type,strtotime($begin),$end) : $this->getKeyValue($info,date('H'),0,':00');
    }

    /**
     * 活跃会员
     */
    private function getActiveMember($type,$start,$end,$sign,$begin){
        return $this->getMemberNumber($type,$start,$end,$sign,$begin);
    }

    /**
     * 付费活跃会员
     */
    private function getActiveConsumeMember($type,$start,$end,$sign,$begin){
        return $this->getConsumeMemberNumber($type,$start,$end,$sign,$begin);
    }

    private function getDataKeyValue($info,$type,$start,$end){

//        $keys = $values = $back = [];
//        if($info){
//            foreach ($info as $item) {
//                $hour = date($type, $item);
//                isset($back[$hour]) ? $back[$hour]++ :  $back[$hour] = 1 ;
//            }
//            for($i = $start;$i <= $end;$i++)
//            {
//                $date =str_pad($i,2,0,STR_PAD_LEFT);
//                $keys[] = $date;
//                $values[] = isset($back[$date]) ? $back[$date] : 0;
//            }
//        }
//        return ['keys'=>$keys,'values'=>$values];
        $keys = $values = $back = [];
        if ($info) {
            foreach ($info as $item) {
                $hour = date($type, $item);
                isset($back[$hour]) ? $back[$hour]++ : $back[$hour] = 1;
            }
            if($type){
                if($type == 'Ym') {
                    $plus = "+1 month";
                    $formate = 'Y/m';
                }elseif($type == 'Ymd'){
                    $plus = "+1 day";
                    $formate = 'm/d';
                }elseif($type == 'YmdH'){
                    $plus = "+1 hour";
                    $formate = 'H:00';
                }
                for ($k = $start; $k<$end; $k = strtotime($plus,$k)){
                    $date1 = date($formate,$k);
                    $date2 = date($type,$k);
                    $keys[] = $date1;
                    $values[] = isset($back[$date2]) ? $back[$date2] : 0;
                }
            }
        }
        return ['keys' => $keys, 'values' => $values];
    }

    /**
     * 获取实时数据
     */
    private function getRealTime(){
        $result = Cache::get('user:chart:hour:'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $type = 'YmdH';
            $start_time = strtotime(date('Y-m-d 00:00:00',time()));
            $member = $this->getMemberNumber($type,$start_time,time(),1,date('Y-m-d 00:00:00'));
            $consume_member = $this->getConsumeMemberNumber($type,$start_time,time(),1,date('Y-m-d 00:00:00'));
            $active = $this->getActiveMember($type,$start_time,time(),1,date('Y-m-d 00:00:00'));
            $active_consume = $this->getActiveConsumeMember($type,$start_time,time(),1,date('Y-m-d 00:00:00'));
            $data = [
                'member'    => $member,
                'consume'   => $consume_member,
                'active'    => $active,
                'active_consume'    => $active_consume,
            ];
            Cache::put('user:chart:hour:'.$this->shop['id'],json_encode($data),EXPIRE_HOUR);
            return $data;

        }


    }

    /**
     * 处理时间坐标
     * @param $data
     * @param string $end
     * @param int $start
     * @param string $str
     * @param int $num
     * @param string $filter
     * @return array
     */
    private function getKeyValue($data,$end = '',$start = 0,$str = '',$num = 2 ,$filter = '0')
    {
        $back = [];
        foreach ($data as $item) {
            $hour = intval(date('H', $item));
            isset($back[$hour]) ? $back[$hour]++ : $back[$hour] = 1 ;
        }
        $keys = $values = [];
        for($i = $start;$i <= $end;$i++)
        {
            $keys[] = str_pad($i,$num,$filter,STR_PAD_LEFT).$str;
            $values[] = isset($back[$i]) ? $back[$i] : 0;
        }
        return array('keys'=>$keys,'values'=>$values);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 订单数统计（全部、今日、昨日）
     */
    public function orderTotal(){
        $param = ['seller_uid' => $this->shop['id']];
        $client = $this->initClient(); //初始化 client
        $url = config('define.order_center.api.order_total');
        try {
            $res1 = $client->request('GET',  $url,['query'=>$param]);
        }catch (\Exception $exception){
            event(new CurlLogsEvent($exception->getMessage(),$client,$url));
            $this->error('error_order');
        }
        $result = $this->errorOrderReturn($res1); //出错处理和接收数据
        event(new CurlLogsEvent(json_encode($result),$client,$url));

        $url = config('define.order_center.api.order_overview');
        try {
            $res2 = $client->request('GET', $url);
        }catch (\Exception $exception){
            event(new CurlLogsEvent($exception->getMessage(),$client,$url));
            $this->error('error_order');
        }
        $overview_result = $this->errorOrderReturn($res2); //出错处理和接收数据
        event(new CurlLogsEvent(json_encode($result),$client,$url));
        
        return $this->output([
            'order_total'       => $result['result']['order_amount'],
            'today_order_total'       => $result['result']['today_order_amount'],
            'yesterday_order_total'  => $overview_result['result']['yesterday_order_pay_total'],
        ]);
    }

    private function errorOrderReturn($res){
        if($res->getStatusCode() !== 200){
            $this->error('error-sync-order');
        }
        $data = json_decode($res->getBody()->getContents(),1);

        if($res && $data['error_code'] && $data['error_code'] !=6018){
            $this->errorWithText(
                'error-sync-order-'.$data['error_code'],
                $data['error_message']
            );
        }elseif($data['error_code'] == 6018){
            $data['result'] = [
                'order_total' => 0,
                'today_order_total' => 0,
                'yesterday_order_total' => 0,
            ];
        }
        return $data;
    }

    /**
     * 今日新增收入
     */
    public function incomeTotal(){
        $param = $this->paramTotal();
        $client = $this->initClient(); //初始化 client
        $url = config('define.order_center.api.order_total');
        try {
            $res = $client->request('GET',  $url,['query'=>$param]);
        }catch (\Exception $exception){
            event(new CurlLogsEvent($exception->getMessage(),$client,$url));
            $this->error('error_order');
        }
        $result = $this->errorReturn($res); //出错处理和接收数据
        event(new CurlLogsEvent(json_encode($result),$client,$url));
        return $this->output([
            'todayIncome'       => $result['result']['seller_today_total']/100,
            'totalIncome'       => $result['result']['seller_total']/100,
            'yesterdayIncome'  => $result['result']['yesterday_total']/100,
        ]);
    }

    /**
     * 总收入参数
     */
    private function paramTotal(){
        return [
            'seller_uid' => $this->shop['id']
        ];
    }
    private function initClient($data = '',$method = 'get')
    {
        $client = hg_verify_signature([],'', '', '',$this->shop['id']);
        return $client;
    }

    private function errorReturn($res)
    {
        if($res->getStatusCode() !== 200){
            $this->error('error-sync-order');
        }
        $data = json_decode($res->getBody()->getContents(),1);

        if($res && $data['error_code'] && $data['error_code'] !=6018){
            $this->errorWithText(
                'error-sync-order-'.$data['error_code'],
                $data['error_message']
            );
        }elseif($data['error_code'] == 6018){
            $data['result'] = [
                'seller_today_total' => 0,
                'seller_total' => 0,
                'yesterday_total' => 0,
            ];
        }
        return $data;
    }



    /**
     * 收入增长统计
     */
    public function incomeGrowth()
    {
        $this->validateWithAttribute([
            'type'  => 'numeric',
        ],[
            'type'  => '类型',
        ]);
        $info = $this->getIncomeTypeData();
        $response = $this->getSearchData($info);
        return $this->output($response);
    }

    /**
     * 订单增长统计
     */
    public function orderGrowth()
    {
        $this->validateWithAttribute([
            'type'  => 'numeric',
        ],[
            'type'  => '类型',
        ]);
        $response = $this->getOrderTypeData();
        return $this->output($response);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 各类型订单数占比
     */
    public function orderPercent(){
        $orders = $this->allOrder();
        $keyArray = ['article','video','audio','column','live','course','member_card','community'];
        $data = [];
        if($keyArray) {
            $total = array_sum($orders);
            foreach ($keyArray as $key=>$val) {
                $amount = isset($orders[$val]) ? $orders[$val] : 0;
                $data[] = [
                    'name' => $val,
                    'value' => $amount,
                    'percent' => $amount ? round($amount / $total * 100, 2) : 0,
                ];
            }
        }
        return $this->output($data);
    }

    /**
     * 个类型收入占比
     */
    public function incomePercent(){

        $income = $this->allIncome();
        $keyArray = ['article','video','audio','column','live','course','member_card','community'];
        $data = [];
        if($keyArray) {
            $total = array_sum($income);
            foreach ($keyArray as $key=>$val) {
                $amount = isset($income[$val]) ? $income[$val] : 0;
                $data[] = [
                    'name' => $val,
                    'value' => $amount,
                    'percent' => $amount ? round($amount / $total * 100, 2) : 0,
                ];
            }
        }
        return $this->output($data);
    }

    /**
     * 获取收入不同条件下的统计数据
     * @return mixed
     */
    private function getIncomeTypeData(){
        switch(request('type')){
            case 1: //按天
                $ret = $this->getIncomeDayData();
                break;
            case 2: //按月
                $ret = $this->getIncomeMonthData();
                break;
            case -1://自定义
                $ret = $this->getIncomeCustomTime();
                break;
            default: //实时
                $ret = $this->getIncomeRealTime();
                break;
        }
        return $ret;
    }

    private function getOrderTypeData(){
        switch(request('type')){
            case 1: //按天
                $ret = $this->getOrderDayData();
                break;
            case 2: //按月
                $ret = $this->getOrderMonthData();
                break;
            case -1://自定义
                $ret = $this->getOrderCustomTime();
                break;
            default: //实时
                $ret = $this->getOrderRealTime();
                break;
        }
        return $ret;
    }

    /**
     * 自定义时间
     */
    private function getOrderCustomTime(){
        $start_time = strtotime(request('start_time'));
        $end_time = strtotime(request('end_time'));
        $data = $this->getOrderNumber('Ymd',$start_time,$end_time);
        return $data;

    }

    /**
     * 按天
     * @return array
     */
    private function getIncomeDayData(){
        $result = Cache::get('income:chart:day:'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $data = $this->getIncomeNumber('Ymd',strtotime(date('Y-m-01 00:00:00')),time());
            Cache::put('income:chart:day:'.$this->shop['id'],json_encode($data),EXPIRE_DAY/60);
            return $data;
        }
    }

    private function getOrderDayData(){
        $result = Cache::get('order:chart:day:'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $data = $this->getOrderNumber('Ymd',strtotime(date('Y-m-01 00:00:00')),time());
            Cache::put('order:chart:day:'.$this->shop['id'],json_encode($data),EXPIRE_DAY/60);
            return $data;
        }
    }

    private function getOrderMonthData(){
        $result = Cache::get('order:chart:month:'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $data = $this->getOrderNumber('Ym',strtotime(date('2017-05-01 00:00:00')),time());
            Cache::put('order:chart:month:'.$this->shop['id'],json_encode($data),EXPIRE_MONTH/60);
            return $data;
        }
    }

    private function getOrderRealTime(){
        $result = Cache::get('order:chart:hour:'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $start_time = strtotime(date('Y-m-d 00:00:00'));
            $info = $this->getOrder($start_time,time());
            $keys = $values = $back = [];
            foreach ($info as $key=>$item) {
                $hour = date('YmdH', $item['pay_time']);
                isset($back[$hour]) ? $back[$hour]+=1  : $back[$hour] = 1;
            }
            $plus = "+1 hour";
            $formate = 'H:00';
            for($i = $start_time;$i <= time();$i= strtotime($plus,$i))
            {
                $date1 = date($formate,$i);
                $date2 = date('YmdH',$i);
                $keys[] = $date1;
                $values[] = isset($back[$date2]) ? $back[$date2] : 0;
            }
            $data = [
                'keys' => $keys,
                'values' => $values
            ];
            Cache::put('order:chart:hour:'.$this->shop['id'],json_encode($data),EXPIRE_HOUR/60);
            return $data;
        }
    }

    private function getIncomeCustomTime(){
        $start_time = strtotime(request('start_time'));
        $end_time = strtotime(request('end_time'));
        $data = $this->getIncomeNumber('Ymd',$start_time,$end_time);
        return $data;
    }

    /**
     * 按月
     * @return array
     */
    private function getIncomeMonthData(){
        $result = Cache::get('income:chart:month:'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $data = $this->getIncomeNumber('Ym',strtotime(date('2017-05-01 00:00:00')),time());
            Cache::put('income:chart:month:'.$this->shop['id'],json_encode($data),EXPIRE_MONTH/60);
            return $data;
        }
    }

    private function getIncomeRealTime(){
        $result = Cache::get('income:chart:hour:'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $start_time = strtotime(date('Y-m-d 00:00:00'));
            $info = $this->getIncome($start_time,time());
            $keys = $values = $back = [];
            foreach ($info as $key=>$item) {
                $hour = date('YmdH', $item['order_time']);
                isset($back[$hour]) ? $back[$hour]+=$item['price']  : $back[$hour] = $item['price'];
            }
            $plus = "+1 hour";
            $formate = 'H:00';
            for($i = $start_time;$i <= time();$i= strtotime($plus,$i))
            {
                $date1 = date($formate,$i);
                $date2 = date('YmdH',$i);
                $keys[] = $date1;
                $values[] = isset($back[$date2]) ? $back[$date2] : 0;
            }
            $data = [
                'keys' => $keys,
                'values' => $values
            ];
            Cache::put('income:chart:hour:'.$this->shop['id'],json_encode($data),EXPIRE_HOUR/60);
            return $data;
        }
    }

    private function getOrderNumber($type,$begin,$end){
        $info = $this->getOrder(0,time());
        $back = $keys = $values = [];
        if ($info) {
            foreach ($info as $data => $item) {
                $hour = date($type, $item['order_time']);
                isset($back[$hour]) ? $back[$hour] += 1 : $back[$hour]= 1;
            }
            if($type){
                if($type == 'Ym') {
                    $plus = "+1 month";
                    $formate = 'Y/m';
                }elseif($type == 'Ymd'){
                    $plus = "+1 day";
                    $formate = 'm/d';
                }elseif($type == 'YmdH'){
                    $plus = "+1 hour";
                    $formate = 'H:00';
                }
                for ($k = $begin; $k<$end; $k = strtotime($plus,$k)){
                    $date1 = date($formate,$k);
                    $date2 = date($type,$k);
                    $keys[] = $date1;
                    $values[] = isset($back[$date2]) ? $back[$date2] : 0;
                }
            }
        }
        return ['keys' => $keys, 'values' => $values];
    }

    /**
     * 获取收入数
     * @param $type
     * @return array
     */
    private function getIncomeNumber($type,$begin,$end){
        $info = $this->getIncome(0,time());
        $back = $keys = $values = [];
//        if($info){
//            foreach ($info as $key=>$item) {
//                $hour = date($type, $item['order_time']);
//                isset($back[$hour]) ? $back[$hour] += $item['price'] : $back[$hour]=$item['price'];
//            }
//            for($i = $begin;$i <= date($type);$i++)
//            {
//                $date = str_pad($i,2,0,STR_PAD_LEFT);
//                $keys[] = $date;
//                $values[] = isset($back[$date]) ? sprintf('%.2f',$back[$date]) : 0;
//            }
//            $list = ['keys'=>$keys,'values'=>$values];
//        }
//        return $list ? : [];

        if ($info) {
            foreach ($info as $data => $item) {
                $hour = date($type, $item['order_time']);
                isset($back[$hour]) ? $back[$hour] += $item['price'] : $back[$hour]=$item['price'];
            }
            if($type){
                if($type == 'Ym') {
                    $plus = "+1 month";
                    $formate = 'Y/m';
                }elseif($type == 'Ymd'){
                    $plus = "+1 day";
                    $formate = 'm/d';
                }elseif($type == 'YmdH'){
                    $plus = "+1 hour";
                    $formate = 'H:00';
                }
                for ($k = $begin; $k<$end; $k = strtotime($plus,$k)){
                    $date1 = date($formate,$k);
                    $date2 = date($type,$k);
                    $keys[] = $date1;
                    $values[] = isset($back[$date2]) ? $back[$date2] : 0;
                }
            }
        }
        return ['keys' => $keys, 'values' => $values];
    }

    /**
     * 获取收入数据
     * @param $start
     * @param $end
     * @return mixed
     */
    private function getIncome($start,$end){
        $where = [
            'shop_id'    => $this->shop['id'],
            'pay_status' => 1,
        ];
        return Order::where($where)
            ->whereBetween('order_time',[$start,$end])
            ->orderBy('order_time','asc')
            ->select('price','order_time')
            ->get();
    }

    private function getOrder($start,$end){
        $where = [
            'shop_id'    => $this->shop['id'],
            'pay_status' => 1,
        ];
        return Order::where($where)
            ->whereBetween('order_time',[$start,$end])
            ->orderBy('order_time','asc')
            ->select('id','order_time')
            ->get();
    }

    /**
     * 用户增长趋势统计
     */
    public function userGrowth()
    {
        $this->validateWithAttribute([
            'type'          => 'numeric',
        ],[
            'type'      => '类型'
        ]);
        $info = $this->get_type_data();
        $response = $this->getSearchData($info);
        return $this->output($response);
    }

    public function userDataGrowth()
    {
        $this->validateWithAttribute([
            'type'          => 'numeric',
        ],[
            'type'      => '类型'
        ]);
        $info = $this->getTypeData(request('type'));
        return $this->output($info);
    }

    protected function getTypeData($type)
    {
        switch($type){
            case 1: //按天
                $ret = Cache::get('user:chart:day:'.$this->shop['id']);
                if($ret){
                    $ret = json_decode($ret);
                }else{
                    $ret = $this->getMemberData('Ymd', strtotime(date('Y-m-01 00:00:00')));
                    cache::put('user:chart:day:'.$this->shop['id'],json_encode($ret),EXPIRE_DAY/60);
                }
                break;
            case 2: //按月
                $ret = Cache::get('user:chart:month:'.$this->shop['id']);
                if($ret){
                    $ret = json_decode($ret);
                }else{
                    $ret = $this->getMemberData('Ym', strtotime(date('2017-05-01 00:00:00')));
                    Cache::put('user:chart:month:'.$this->shop['id'],json_encode($ret),EXPIRE_MONTH/60);
                }
                break;
            default: //实时
                    $ret = $this->getRealTime();
                break;
        }
        return $ret;
    }

    protected function getMemberData($type,$start)
    {
        $data = CronStatistics::select('member','paid_member','create_time','year','month','day')->where('shop_id',$this->shop['id'])->whereBetween('create_time',[$start,time()])->orderBy('create_time')->get();
        if($type == 'Ym') {
            $plus = "+1 month";
            $formate = 'Y/m';
        }elseif($type == 'Ymd'){
            $plus = "+1 day";
            $formate = 'm/d';
        }
        $result = $temp = [];
        if(!$data->isEmpty()){
            foreach($data as $value){
                $hour = date($type, $value->create_time);
                if(isset($temp[$hour])){
                    $temp[$hour] = [
                        'member' => $value->member+$temp[$hour]['member'],
                        'consume' => $value->paid_member+$temp[$hour]['consume'],
                    ];
                }else{
                    $temp[$hour] = [
                        'member' => $value->member,
                        'consume' => $value->paid_member,
                    ];
                }
            }
            for($k = $start;$k < strtotime(date('Y-m-d',time())); $k = strtotime($plus,$k)){
                $date2 = date($type,$k);
                $result['member']['keys'][] = date($formate,$k);
                $result['member']['values'][] = isset($temp[$date2]) ? $temp[$date2]['member'] : 0 ;

                $result['consume']['keys'][] = date($formate,$k);
                $result['consume']['values'][] = isset($temp[$date2]) ? $temp[$date2]['consume'] : 0 ;
            }
        }
        return $result;
    }

    /**
     * 用户分布
     */
    public function userDistribute()
    {
        $sexAndLangDistribute = $this->sexAndLangDistribute();
        return $this->output($sexAndLangDistribute);
    }

    /**
     * 性别分布,语言分布
     * @return mixed
     */
    private function sexAndLangDistribute(){
        $info = Member::where('shop_id',$this->shop['id'])
            ->orderBy('create_time','desc')
            ->get(['sex','create_time','language','province','source']);
        $sexInfo = $langInfo = $areaInfo = $data = $sourceInfo = [];
        if(!$info->isEmpty()){
            $sex = [ 0=>'other',1=>'man',2=>'woman'];
            foreach ($info as $key=>$item) {
                $item->sex = isset($sex[$item->sex]) ? : 'other';
                $item->language = $item->language ? : 'unknown';
                $item->province = $item->province ? : 'unknown';
                $item->source = $item->source ? : 'unknown';
                $sexInfo[$item->sex] = isset($sexInfo[$item->sex]) ? $sexInfo[$item->sex]+1 : 1;
                $langInfo[$item->language]= isset($langInfo[$item->language]) ? $langInfo[$item->language]+1 : 1;
                $areaInfo[$item->province] = isset($areaInfo[$item->province]) ? $areaInfo[$item->province]+1 : 1;
                $sourceInfo[$item->source] = isset($sourceInfo[$item->source]) ? $sourceInfo[$item->source]+1 : 1;
            }

            if($sexInfo || $langInfo ||$areaInfo){
                $data['sex']['values'] = $this->getSexNumber($sexInfo,$sex);
                $data['lang']['values'] = $this->getLangNumber($langInfo);
                $data['area']['values'] = $this->getAreaNumber($areaInfo);
                $data['source']['values'] = $this->getSourceNumber($sourceInfo);
            }
        }
        return $data;

    }

    /**
     * 处理来源分布
     * @param $data
     * @return array
     */
    private function getSourceNumber($data)
    {
        $source = [ 'wechat','applet','app'];
        $return = [];
        foreach ($source as $key=>$value){
            $return[$value]['name'] = $value;
            $return[$value]['data'][] = isset($data[$value]) ? $data[$value] : 0;
        }
        return $return;
    }

    /**
     * 处理性别分布
     * @param $data
     * @param $sex
     * @param $date
     * @return array
     */
    private function getSexNumber($data,$sex){
        $return = [];
        if($sex){
            foreach($sex as $key=>$value){
                $return[$value]['name'] = $value ? : 'other';
                $return[$value]['data'][] = isset($data[$value]) ? $data[$value] : 0;
            }
        }
        return $return ? : [];
    }

    /**
     * 处理语言分布
     * @param $data
     * @param $date
     * @return array
     */
    private function getLangNumber($data){
        $return = [];
        $lang = ['zh_CN','en','unknown','zh_trad'];
        if($lang){
            foreach($lang as $key=>$value){
                $return[$value]['name'] = $value ? : '未知';
                $return[$value]['data'][] = isset($data[$value]) ? $data[$value] : 0;
            }
        }
        return $return ? : [];
    }

    /**
     * 地区分布
     * @param $data
     * @param $date
     * @return array
     */
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
     * 语言分布
     * @return mixed
     */
    private function langDistribute(){
        $lang = Member::where('shop_id',$this->shop['id'])
//            ->where('language','!=','')
            ->orderBy('create_time','desc')
            ->get(['create_time','language']);
        $info = [];
        if(!$lang->isEmpty()){

            foreach ($lang as $key=>$item) {
                $date = date('Y/m/d',$item->create_time);
                $item->language = $item->language ? : '未知';
                $keys[$date] = 0 ;
                $values[$item->language][$date] = isset($value[$item->language][$date]) ? $value[$item->language][$date]+1 : 1;
            }
            if($values){
                foreach ($values as $key=>$value) {
                    $info[$key]['name'] = $key ? : '未知';
                    $info[$key]['data'] = array_values(array_merge($keys,$value));
                }
            }
        }
        return $info;
    }

    /**
     * 活跃用户统计
     */
    public function activeUserGrowth()
    {

    }

    /**
     * 今日概况
     */
    public function todaySituation(){
        $start_time = strtotime(date('Y-m-d 00:00:00',time()));
        $todayComment = Comment::where('shop_id',$this->shop['id'])->whereBetween('comment_time',[$start_time,time()])->count();
        $todayFeedback = FeedBack::where('shop_id',$this->shop['id'])->whereBetween('feedback_time',[$start_time,time()])->count();
        $todayMember = Member::where('shop_id',$this->shop['id'])->whereBetween('create_time',[$start_time,time()])->count();
        $allMember = Member::where('shop_id',$this->shop['id'])->count();
        $result = [
            'todayComment'    => $todayComment,
            'todayFeedback'   => $todayFeedback,
            'todayMember'     => $todayMember,
            'allMember'       => $allMember
        ];
        return $this->output(['data' => $result]);
    }

    public function analysisOrder(){

    }

    /**
     * 内容分析
     * @return mixed
     */
    public function analysisContent()
    {
        $start_time = strtotime(date('Y-m-d 00:00:00',time()));
        $todayOrder = $this->todayOrder($start_time);  //今日订单数
        $todayViews = array_merge($this->todayFourViews($start_time),$this->todayColumnViews($start_time));
        $allOrder = $this->allOrder($start_time);      //总订单
        $todayIncome = $this->todayIncome($start_time);  //今日新增收入
        $allIncome = $this->allIncome();                 //总收入
        $allView = array_merge($this->allView(),$this->allMemberCard(),$this->allColumn());   //总浏览量
        $keyArray = ['article','audio','video','live','column','course','member_card','community'];
        $data = [];
        foreach ($keyArray as $key => $item){
            $torder = isset($todayOrder[$item]) ? $todayOrder[$item] : 0;
            $tviews = isset($todayViews[$item]) ? $todayViews[$item] : 0;
            $tincome = isset($todayIncome[$item]) ? $todayIncome[$item] : 0;
            $aorder = isset($allOrder[$item]) ? $allOrder[$item] : 0;
            $aview = isset($allView[$item]) ? $allView[$item] : 0;
            $aincome = isset($allIncome[$item]) ? $allIncome[$item] : 0;
            $data[] =[
                'type'        => $item,
                'todayOrder'  => $torder,
                'todayView'   => $tviews,
                'todayIncome' => $tincome,
                'allOrder'    => $aorder,
                'allView'     => $aview,
                'allIncome'   => $aincome
            ];
        }
        return $this->output($data);
    }

    /**
     * 今日订单数量
     * @param $start_time
     * @return mixed
     */
    private function todayOrder($start_time){
        $result= Order::where(['shop_id' => $this->shop['id'],'pay_status' => 1])
            ->whereBetween('order_time',[$start_time,time()])
            ->groupBy('content_type')
            ->select(DB::raw('count(id) as todayOrder'),'content_type as type')->pluck('todayOrder','type');
        $card_record = CardRecord::where(['shop_id' => $this->shop['id']])
            ->whereBetween('order_time',[$start_time,time()])
            ->count('id');
        $card_order = ['member_card'=>$card_record];
        $results = array_merge($card_order,$result->toArray());
        return $results;
    }

    /**
     * 今日浏览量 前4种
     * @param $start_time
     * @return mixed
     */
    private function todayFourViews($start_time){
        $result = Views::where('shop_id',$this->shop['id'])
            ->whereBetween('view_time',[$start_time,time()])
            ->groupBy('content_type')
            ->select(DB::raw('count(id) as todayView'),'content_type as type')->pluck('todayView','type')->toArray();
        return $result;
    }

    /**
     * 今日浏览量  专栏
     * @param $start_time
     * @return mixed
     */
    private function todayColumnViews($start_time){
        $result = Views::where('shop_id',$this->shop['id'])
            ->where('content_column','<>',0)
            ->whereBetween('view_time',[$start_time,time()])
            ->select(DB::raw('count(id) as todayView,"column" as type'))->pluck('todayView','type')->toArray();
        return $result;
    }

    /**
     * 总订单数量
     * @param $start_time
     * @return mixed
     */
    private function allOrder($start_time=''){
        $result= Order::where(['shop_id' => $this->shop['id'],'pay_status' => 1])
            ->groupBy('content_type')
            ->select(DB::raw('count(id) as allOrder'),'content_type as type')->pluck('allOrder','type');
        $card_record = CardRecord::where(['shop_id' => $this->shop['id']])
            ->count('id');
        $card_order = ['member_card'=>$card_record];
        $results = array_merge($card_order,$result->toArray());
        return $results;
    }


    /**
     * 今日收入
     * @param $start_time
     * @return mixed
     */
    private function todayIncome($start_time){
        $result= Order::where(['shop_id'=> $this->shop['id'],'pay_status' => 1])
            ->whereBetween('order_time',[$start_time,time()])
            ->groupBy('content_type')
            ->select(DB::raw('sum(price) as todayIncome'),'content_type as type')->pluck('todayIncome','type');
        $card_record = CardRecord::where(['shop_id' => $this->shop['id']])
            ->whereBetween('order_time',[$start_time,time()])
            ->sum('price');
        $card_order = ['member_card'=>$card_record];
        $results = array_merge($card_order,$result->toArray());
        return $results;

    }

    /**
     * 总收入
     * @return mixed
     */
    private function allIncome()
    {
        $result= Order::where(['shop_id'=> $this->shop['id'],'pay_status' => 1])
            ->groupBy('content_type')
            ->select(DB::raw('sum(price) as allIncome'),'content_type as type')
            ->pluck('allIncome','type');
        $card_record = CardRecord::where(['shop_id' => $this->shop['id']])
            ->where('order_id', '!=', '-1')
            ->sum('price');
        $card_order = ['member_card'=>$card_record];
        $results = array_merge($card_order,$result->toArray());
        return $results;

    }

    /**
     * 内容类型的总阅读数目
     * @return mixed
     */
    private function allView()
    {
        $result= Content::where('shop_id', $this->shop['id'])
            ->groupBy('type')
            ->select(DB::raw('sum(view_count) as allView'),'type')->pluck('allView','type')->toArray();
        return $result;
    }

    private function allMemberCard(){
        $result = Views::where(['shop_id'=>$this->shop['id'],'content_type'=>'member_card'])
            ->groupBy('content_type')
            ->select(DB::raw('count(id) as allView'),'content_type as type')->pluck('allView','type')->toArray();
        return $result;
    }

    /**
     * 专栏总阅读数
     * @return mixed
     */
    private function allColumn()
    {
        $result= Content::where('shop_id', $this->shop['id'])
            ->where('column_id','<>',0)
            ->select(DB::raw('sum(view_count) as allView,"column" as type'))->pluck('allView','type')->toArray();
        return $result;
    }

    /**
     * 课程总阅读数据
     * @return int
     */
    private function allCourse(){
        $total = ClassContent::where('shop_id',$this->shop['id'])->sum('view_count');
        return ['course'=>intval($total)];
    }


    /**
     * 今日内容-折线图分析
     */
    public function chartAnalysis(){
        $this->validateWithAttribute([
            'type'    => 'alpha_dash',
            'time'    => 'numeric'
        ]);
        $info = $this->getTimeType();
        $result = $this->getSearchData($info);
        return $this->output($result);
    }

    public function chartDataAnalysis()
    {
        $this->validateWithAttribute([
            'time'    => 'numeric'
        ]);
        $info = $this->getTimeDataType(request('time'));
        return $this->output($info);
    }

    protected function getTimeDataType($time)
    {
        switch($time){
            case 1://按天
                $type = request('type') ? : 'all';  //redis 里面键值
                $ret = Cache::get('content:chart:day:'.$type.':'.$this->shop['id']);
                if($ret){
                    $ret = json_decode($ret);
                }else{
                    $ret = $this->getDayMonthDataDetail('Ymd',strtotime(date('Y-m-01 00:00:00')));
                    Cache::put('content:chart:day:'.$type.':'.$this->shop['id'],json_encode($ret),EXPIRE_DAY/60);
                }
                break;
            case 2://按月
                $type = request('type') ? : 'all';  //redis 里面键值
                $ret = Cache::get('content:chart:month:'.$type.':'.$this->shop['id']);
                if($ret){
                    $ret = json_decode($ret);
                }else{
                    $ret = $this->getDayMonthDataDetail('Ym',strtotime(date('2017-05-01 00:00:00')));
                    Cache::put('content:chart:month:'.$type.':'.$this->shop['id'],json_encode($ret),EXPIRE_MONTH/60);
                }
                break;
            default://按时
                $ret = $this->getIncreaseRealTime();
                break;
        }
        return $ret;
    }

    protected function getDayMonthDataDetail($type,$start)
    {
        $data = CronStatistics::select('yesterday_income','click_num','order_num','create_time','year','month','day')->where('shop_id',$this->shop['id'])->whereBetween('create_time',[$start,time()])->orderBy('create_time')->get();
        if($type == 'Ym') {
            $plus = "+1 month";
            $formate = 'Y/m';
        }elseif($type == 'Ymd'){
            $plus = "+1 day";
            $formate = 'm/d';
        }
        $result = $temp = [];
        if(!$data->isEmpty()){
            foreach($data as $value){
                $hour = date($type, $value->create_time);
                if(isset($temp[$hour])){
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
            for($k = $start;$k < strtotime(date('Y-m-d',time())); $k = strtotime($plus,$k)){
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
        switch(request('time')){
            case 1: //按天
                $ret = $this->getIncreaseDayData();
                break;
            case 2: //按月
                $ret = $this->getIncreaseMonthData();
                break;
            default: //实时
                $ret = $this->getIncreaseRealTime();
                break;
        }
        return $ret;
    }

    /**
     * 按天
     */
    private function getIncreaseDayData(){
        $type = request('type') ? : 'all';  //redis 里面键值
        $result = Cache::get('content:chart:day:'.$type.':'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $data = $this->getDayMonthDetail('Ymd',date('Y-m-01 00:00:00'));
            Cache::put('content:chart:day:'.$type.':'.$this->shop['id'],json_encode($data),EXPIRE_DAY/60);
            return $data;
        }
    }

    /**
     * 按月
     */
    private function getIncreaseMonthData(){
        $type = request('type') ? : 'all';  //redis 里面键值
        $result = Cache::get('content:chart:month:'.$type.':'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $data = $this->getDayMonthDetail('Ym',date('2017-05-01 00:00:00'));
            Cache::put('content:chart:month:'.$type.':'.$this->shop['id'],json_encode($data),EXPIRE_MONTH/60);
            return $data;
        }
    }

    /**
     * 按时
     */
    private function getIncreaseRealTime(){
        $type = request('type') ? : 'all';  //redis 里面键值
        $result = Cache::get('content:chart:hour:'.$type.':'.$this->shop['id']);
        if($result){
            return json_decode($result);
        }else{
            $start_time = strtotime(date('Y-m-d 00:00:00',time()));
            $orderInfo = $this->increaseOrder('YmdH',$start_time,time(),date('Y-m-d 00:00:00'));
            $viewInfo = $this->increaseView('YmdH',$start_time,time(),date('Y-m-d 00:00:00'));
            $incomeInfo = $this->increaseIncome('YmdH',$start_time,time(),date('Y-m-d 00:00:00'));
            $data = [
                'order'  => $orderInfo,
                'view'   => $viewInfo,
                'income' => $incomeInfo
            ];
            Cache::put('content:chart:hour:'.$type.':'.$this->shop['id'],json_encode($data),EXPIRE_HOUR/60);
            return $data;
        }
    }

    /**
     * 天，月类型的
     * @param $type
     * @return array
     */
    private function getDayMonthDetail($type,$begin){
        $orderInfo = $this->increaseOrder($type,0,time(),$begin); //订单
        $viewInfo = $this->increaseView($type,0,time(),$begin);   //浏览量
        $incomeInfo = $this->increaseIncome($type,0,time(),$begin); //收入
        return $result = [
            'order'  => $orderInfo,
            'view'   => $viewInfo,
            'income' => $incomeInfo
        ];
    }

    /**
     * 新增收入查询
     * @param $start
     * @param $end
     * @return array
     */
    private function increaseIncome($type,$start,$end,$begin){
        $order = Order::where(['shop_id'=>$this->shop['id'],'pay_status'=> 1]);
        request('type') && $order->where('content_type',request('type'));
        $res = $order
            ->whereBetween('order_time',[$start,$end])
            ->select('price','order_time as same_time')->get();
        if(request('time') == 1 || request('time') == 2){
            return $this->getDataKeyValueIncome($res,$type,strtotime($begin),$end);
        }else{
            return $this->getTypeIncrease($res,1);
        }
    }

    /**
     * 新增订单查询
     * @param $start
     * @param $end
     * @return array
     */
    private function increaseOrder($type,$start,$end,$begin){
        $order = Order::where(['shop_id' => $this->shop['id'],'pay_status' => 1]);
        request('type') && $order->where('content_type',request('type'));
        $res = $order
            ->whereBetween('order_time',[$start,$end])
            ->select(DB::raw('1 as price'),'order_time as same_time')->get();
        if(request('time') == 1 || request('time') == 2){
            return $this->getDataKeyValueIncrease($res,$type,strtotime($begin),$end);
        }else{
            return $this->getTypeIncrease($res);
        }
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
    private function increaseView($type,$start,$end,$begin){
        $view = Views::where('shop_id',$this->shop['id']);
        if(!request('type') || request('type') != 'column'){ //type不是专栏类型
            request('type') && $view->where('content_type',request('type'));
            $res = $view
                ->whereBetween('view_time',[$start,$end])
                ->select(DB::raw('1 as price'),'view_time as same_time')->get();
        }else{
            $res = $view
                ->where('content_column','<>',0)
                ->whereBetween('view_time',[$start,$end])
                ->select(DB::raw('1 as price'),'view_time as same_time')->get();
        }
        if(request('time') == 1 || request('time') == 2){
            return $this->getDataKeyValueIncrease($res,$type,strtotime($begin),$end);
        }else{
            return $this->getTypeIncrease($res);
        }
    }

    /**
     * -计算数量
     * @param $info
     * @param $type
     * @return array
     */
    private function getDataKeyValueIncrease($info,$type,$start,$end){

        $list = $back = $keys = $values = [];
        if($info){

            foreach ($info as $key=>$item) {
                $hour = date($type, $item['same_time']);
                isset($back[$hour]) ? $back[$hour]++ :  $back[$hour] = 1 ;
            }
            if($type){
                if($type == 'Ym') {
                    $plus = "+1 month";
                    $formate = 'Y/m';
                }elseif($type == 'Ymd'){
                    $plus = "+1 day";
                    $formate = 'm/d';
                }elseif($type == 'YmdH'){
                    $plus = "+1 hour";
                    $formate = 'H:00';
                }
                for ($k = $start; $k<$end; $k = strtotime($plus,$k)){
                    $date1 = date($formate,$k);
                    $date2 = date($type,$k);
                    $keys[] = $date1;
                    $values[] = isset($back[$date2]) ? $back[$date2] : 0;
                }
            }

            $list = ['keys'=>$keys,'values'=>$values];

        }
        return $list;
    }

    /**
     * 收入-计算price
     * @param $info
     * @param $type
     * @return array
     */
    private function getDataKeyValueIncome($info,$type,$start,$end){
        $list = $back = $keys = $values = [];
        if($info){
            foreach ($info as $key=>$item) {
                $hour = date($type, $item['same_time']);
                isset($back[$hour]) ? $back[$hour] += $item['price'] : $back[$hour]=$item['price'];
            }
            if($type){
                if($type == 'Ym') {
                    $plus = "+1 month";
                    $formate = 'Y/m';
                }elseif($type == 'Ymd'){
                    $plus = "+1 day";
                    $formate = 'm/d';
                }elseif($type == 'YmdH'){
                    $plus = "+1 hour";
                    $formate = 'H:00';
                }
                for ($k = $start; $k<$end; $k = strtotime($plus,$k)){
                    $date1 = date($formate,$k);
                    $date2 = date($type,$k);
                    $keys[] = $date1;
                    $values[] = isset($back[$date2]) ? $back[$date2] : 0;
                }
            }

            $list = ['keys'=>$keys,'values'=>$values];
        }
        return $list ;
    }

    /**
     * 按时计算-返回数据
     * @param $info
     * @return array
     */
    private function getTypeIncrease($info,$flage = 0){
        if($info){
            $back = [];
            foreach ($info as $key=>$item) {
                $hour = intval(date('H', $item['same_time']));
                isset($back[$hour]) ? $back[$hour]+=$item['price']  : $back[$hour]=$item['price'];
            }
            if($flage == 1){ //订单金额，结果保留2位小数
                foreach ($back as $key=>$item) {
                    $item = sprintf('%.2f',$item);
                    $back[$key] = $item;
                }
            }
            $list = $this->getIncreaseKeyValue($back,date('H'),0,':00');
        }
        return $list = $list ? : [];
    }

    /**
     * @param $data
     * @param string $end
     * @param int $start
     * @param string $str
     * @param int $num
     * @param string $filter
     * @return array
     */
    private function getIncreaseKeyValue($data,$end = '',$start = 0,$str = '',$num = 2 ,$filter = '0')
    {
        $keys = $values = [];
        for($i = $start;$i <= $end;$i++)
        {
            $keys[] = str_pad($i,$num,$filter,STR_PAD_LEFT).$str;
            $values[] = isset($data[$i]) ? $data[$i] : 0;
        }
        return array('keys'=>$keys,'values'=>$values);
    }

    /**
     * 优质内容-top10
     * @return mixed
     */
    public function highContent(){
//        $result = Order::where('order.shop_id','8JWqxzROWeVeBGLag4')
        $shop_id = $this->shop['id'];
        $result = Order::where('order.shop_id',$shop_id)
            ->where('content.up_time','<',time())
            ->where('pay_status',1)
            ->leftJoin('content','order.content_id','content.hashid')
            ->whereColumn('content.type', '=', 'order.content_type')
            ->groupBy('content_id')
            ->orderBy('totalPrice','desc')
            ->limit(10)
            ->select(DB::raw('sum(hg_order.price) as totalPrice'),'content_id','content.title','content.type','content.up_time','content.create_time')->get();
        foreach ($result as $key => $item){
            $item->sort = $key+1;
            $item->up_time = intval($item->up_time) ? : $item->create_time;
            $item->dates = ceil((time() - $item->up_time)/86400);
            $item->up_time = hg_format_date($item->up_time);
        }
        return $this->output(['data' => $result]);
    }


    /**
     *  内容数据统计公用接口
     */
    public function contentStatistics(){
        $this->validateWithAttribute(['content_id'=>'required'],['content_id'=>'内容id']);
        $data = $this->formatTypeData();
        return $this->output($data);
    }

    /**
     * @return mixed
     * 内容统计单独处理
     */
    public function aliveStatistics(){
        $this->validateWithAttribute(['content_id'=>'required'],['content_id'=>'内容id']);
        $data = $this->formatLiveTypeData();
        return $this->output($data);
    }

    /**
     * @return array
     */
    private function formatTypeData(){
        switch(request('type')){
            case 1: $data = $this->selectDayData(); break;    //按天
            case 2: $data = $this->selectMonthData(); break;  //按月
            default: $data = $this->selectRealTime(); break;  //按时
        }
        return $data;
    }

    /**
     * @return array
     */
    private function formatLiveTypeData(){
        switch(request('type')){
            case 1: $data = $this->selectDayData(1); break;    //按天
            case 2: $data = $this->selectMonthData(1); break;  //按月
            default: $data = $this->selectRealTime(1); break;  //按时
        }
        return $data;
    }

    /**
     * 按时
     * @param string $sign
     * @return array
     */
    private function selectRealTime($sign=''){
        $type = 'YmdH';
        $start_time = strtotime(date('Y-m-d 00:00:00',time()));
        $view = $this->getViewNumber($type,$start_time,time(),date('Y-m-d 00:00:00'));
        $comment = $this->getCommentNumber($type,$start_time,time(),date('Y-m-d 00:00:00'),$sign);
        return ['view'=>$view,'comment'=>$comment];
    }

    /**
     * 按天
     * @param string $sign
     * @return array
     */
    private function selectDayData($sign=''){
        $type = 'Ymd';
        $begin = date('Y-m-01 00:00:00');
        $view = $this->getViewNumber($type,0,time(),$begin);
        $comment = $this->getCommentNumber($type,0,time(),$begin,$sign);
        return ['view'=>$view,'comment'=>$comment];
    }

    /**
     * @param string $sign
     * @return array
     *  按月
     */
    private function selectMonthData($sign=''){
        $type = 'Ym';
        $begin = date('2017-05-01 00:00:00');
        $view = $this->getViewNumber($type,0,time(),$begin);
        $comment = $this->getCommentNumber($type,0,time(),$begin,$sign);
        return ['view'=>$view,'comment'=>$comment];
    }

    /**
     * 获取浏览量
     * @param $type
     * @param $start
     * @param $end
     * @param $begin
     * @return array
     */
    private function getViewNumber($type,$start,$end,$begin){
        $info = Views::where(['shop_id'=>$this->shop['id'],'content_id'=>request('content_id')])
            ->whereBetween('view_time',[$start,$end])
            ->orderBy('view_time','asc')
            ->pluck('view_time');
        return $this->getDataKeyValue($info,$type,strtotime($begin),$end);
    }

    /**
     * 获取评论数
     * @param $type
     * @param $start
     * @param $end
     * @param $begin
     * @param string $sign
     * @return array
     */
    private function getCommentNumber($type,$start,$end,$begin,$sign=''){
        if($sign){
            $info = AliveMessage::where(['shop_id'=>$this->shop['id'],'content_id'=>request('content_id')])
                ->where('tag','=','普通会员')
                ->whereBetween('time',[$start,$end])
                ->orderBy('time','asc')
                ->pluck('time');
        } else{
            $info = Comment::where(['shop_id'=>$this->shop['id'],'content_id'=>request('content_id')])
                ->whereBetween('comment_time',[$start,$end])
                ->orderBy('comment_time','asc')
                ->pluck('comment_time');
        }
        return $this->getDataKeyValue($info,$type,strtotime($begin),$end);
    }


    /**
     * 收入概况
     */
    public function overview(){
        $shop_id = $this->shop['id'];
        $today = strtotime(date("Y-m-d"));
        $yesterday = strtotime(date("Y-m-d", strtotime("-1 day")));

        $query_set = Order::where(['shop_id' => $shop_id, 'pay_status'=>1])
            ->whereBetween('order_time', [$yesterday, $today]);
        $order_count = $query_set->count();
        $consumer_count = $query_set->select('user_id')->distinct()->count('user_id');
        $total = $query_set->sum('price');

        $active_member_count = Views::select('member_id')
            ->where(['shop_id' => $shop_id])
            ->whereBetween('view_time', [$yesterday, $today])
            ->distinct()->count('member_id');

        $result = [
            'yesterday' => [
                'consumer_count' => $consumer_count,
                'order_count' => $order_count,
                'total' => $total,
                'active_member_count' => $active_member_count
            ],
            'available' => $this->getAvailable(),
        ];
        return $this->output($result);
    }

    /**
     * 获取提现金额
     */
    private function getAvailable()
    {
        $client = $this->initClient(); //初始化 client
        $url = config('define.order_center.api.withdraw_money');
        $mon = 0;
        try {
            $res = $client->request('GET', $url);
            $result = json_decode($res->getBody()->getContents());; //出错处理和接收数据
            $mon = isset($result->result->available) ? $result->result->available / 100 : 0;
            event(new CurlLogsEvent(json_encode($result), $client, $url));
        } catch (\Exception $exception) {
            $result = $exception->getMessage();
            event(new CurlLogsEvent($result, $client, $url));
        }
        return sprintf('%.2f', $mon);
    }

}