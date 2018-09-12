<?php
namespace App\Http\Controllers\Manage\User;

use App\Http\Controllers\Manage\BaseController;
use App\Jobs\CronContentData;
use App\Models\Manage\Users;
use App\Models\Manage\VersionOrder;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Models\CronStatistics;
use App\Jobs\CronData;

class AnalysisController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 会员基本数据统计
     */
    public function userTotal()
    {
//        $total = Users::count('id');
//        $today_total = Users::whereBetween('created_at', [date('Y-m-d 00:00:00', time()), hg_format_date()])->count('id');
//        return $this->output([
//            'total'       => $total,
//            'today_total' => $today_total
//        ]);
        $total = Shop::count('id');
        $Pctotal = Shop::where('channel','desktop')->count('id');
        $mobileTotal = Shop::where('channel','mobile')->count('id');
        $start = strtotime(date('Y-m-d 00:00:00', time()));
        $end = time();
        $todayTotal = Shop::whereBetween('create_time', [$start,$end])->count('id');
        $todayPcTotal = Shop::whereBetween('create_time', [$start,$end])->where('channel','desktop')->count('id');
        $todayMobileTotal = Shop::whereBetween('create_time', [$start,$end])->where('channel','mobile')->count('id');
        return $this->output([
            'total' => $total,
            'today_total' => $todayTotal,
            'pc_total' => $Pctotal,
            'pc_today_total' => $todayPcTotal,
            'mobile_total' => $mobileTotal,
            'mobile_today_total' => $todayMobileTotal
        ]);
    }

    /**
     * 会员统计图表
     */
    public function userChart()
    {
        $this->validateWith(['type' => 'numeric|in:0,1,2,3','version'=>'alpha_dash|in:basic,advanced,partner']);
//        $data = $this->getUserTotal(request('type'));
//        return $this->output($data);
        $data = $this->getShopTotal(request('type'));
        return $this->output($data);
    }

    public function userDataChart()
    {
        $this->validateWith(['type' => 'numeric|in:0,1,2,3,4,5','version'=>'alpha_dash|in:basic,advanced,partner']);
        $data = $this->getDataShopTotal(request('type'));
        return $this->output($data);
    }

    protected function getDataShopTotal($type)
    {
        switch($type){
            case 5: //自定义
                $key = 'manage:user:day';
                $start = request('start');
                $end = request('end');
                if($end-$start > 30*86400){
                    $this->errorWithText('time_over','时间跨度太大,最多支持30天');
                }
                if(0 && !request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getUserData('Ymd',$start,$end);
//                    Redis::setex($key,4*3600,json_encode($ret));
                }
                break;
            case 4: //按年
                $key = 'manage:user:day';
                $start = request('date');
                $end = strtotime("+1 year",$start)-1;
                if(0 && !request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getUserData('Ym',$start,$end);
//                    Redis::setex($key,4*3600,json_encode($ret));
                }
                break;
            case 3: //自然月
                $key = 'manage:user:month';
                $start = request('date');
                $end = strtotime("+1 month",$start)-1;
                if(0 && !request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getUserData('Ymd',$start,$end);
//                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            case 2: //自然周
                $key = 'manage:user:week';
                $start = request('date');
                $end = strtotime("+1 week",$start)-1;
                if(0 && !request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getUserData('Ymd', $start, $end);
//                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            case 1: //自然天
                $key = 'manage:user:hour';
                $start = request('date');
                $end = strtotime("+1 day",$start)-1;
                if(0 && !request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getData('YmdH', $start , $end);
//                    Redis::setex($key,1800,json_encode($ret));
                }
                break;
            default: //实时
                $key = 'manage:user:hour';
                if(0 && !request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getData('YmdH', strtotime(date('Y-m-d 00:00:00')), time());
//                    Redis::setex($key,1800,json_encode($ret));
                }
                break;
        }
        return $ret;
    }

    protected function getShopTotal($type)
    {
        switch($type){
            case 1: //按天
                $key = 'manage:user:day';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getData('Ymd',strtotime("-60 days"));
                    Redis::setex($key,4*3600,json_encode($ret));
                }
                break;
            case 2: //按月
                $key = 'manage:user:month';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getData('Ym', strtotime(date('2017-05-01 00:00:00')));
                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            case 3: //按周
                $key = 'manage:user:week';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getData('YW', strtotime(date('Y-01-01 00:00:00')));
                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            default: //实时
                $key = 'manage:user:hour';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getData('YmdH', strtotime(date('Y-m-d 00:00:00')));
                    Redis::setex($key,1800,json_encode($ret));
                }
                break;
        }
        return $ret;
    }

    protected function getUserData($type,$start,$end)
    {
        $data = CronStatistics::select('user','paid_user','active_user','create_time','year','month','day','desktop_user','mobile_user')->where('shop_id','total')->whereBetween('create_time',[$start,$end])->orderBy('create_time')->get();
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
                if(isset($temp[$hour])){
                    $temp[$hour] = [
                        'registerUser' => $value->user+$temp[$hour]['registerUser'],
                        'incomeUser' => $value->paid_user+$temp[$hour]['incomeUser'],
                        'activeUser' => $value->active_user+$temp[$hour]['activeUser'],
                        'desktopUser' => $value->desktop_user+$temp[$hour]['desktopUser'],
                        'mobileUser' => $value->mobile_user+$temp[$hour]['mobileUser'],
                    ];
                }else{
                    $temp[$hour] = [
                        'registerUser' => $value->user,
                        'incomeUser' => $value->paid_user,
                        'activeUser' => $value->active_user,
                        'desktopUser' => $value->desktop_user,
                        'mobileUser' => $value->mobile_user,
                    ];
                }
            }
            for($k = $start;$k <= strtotime(date('Y-m-d',$end)); $k = strtotime($plus,$k)){
                $date2 = date($type,$k);
                $result['registerUser']['keys'][] = date($formate,$k);
                $result['registerUser']['values'][] = isset($temp[$date2]) ? $temp[$date2]['registerUser'] : 0 ;

                $result['incomeUser']['keys'][] = date($formate,$k);
                $result['incomeUser']['values'][] = isset($temp[$date2]) ? $temp[$date2]['incomeUser'] : 0 ;

                $result['activeUser']['keys'][] = date($formate,$k);
                $result['activeUser']['values'][] = isset($temp[$date2]) ? $temp[$date2]['activeUser'] : 0 ;

                $result['desktopUser']['keys'][] = date($formate,$k);
                $result['desktopUser']['values'][] = isset($temp[$date2]) ? $temp[$date2]['desktopUser'] : 0 ;


                $result['mobileUser']['keys'][] = date($formate,$k);
                $result['mobileUser']['values'][] = isset($temp[$date2]) ? $temp[$date2]['mobileUser'] : 0 ;
            }
        }
        return $result;
    }

    protected function getData($type,$start,$end = 0)
    {
        $end = $end ?: time();
        $registerUser = $this->getShopNumber($type, $start, $end);
        $incomeUser = $this->getIncomeShop($type, $start, $end);
        $desktopUser = $this->getShopNumber($type, $start, $end,'desktop');
        $mobileUser = $this->getShopNumber($type, $start, $end,'mobile');
        $activeUser = $this->getActiveShop($type, $start, $end);
        return [
            'registerUser' => $registerUser,
            'incomeUser' =>$incomeUser,
            'activeUser' => $activeUser,
            'desktopUser' => $desktopUser,
            'mobileUser' => $mobileUser,
        ];
    }

    protected function getShopNumber($type,$start,$end,$channal = '')
    {
        $shop = Shop::whereBetween('create_time',[$start,$end]);
        request('version') && $shop->where('version',request('version'));
        if($channal) {
            $shop->where('channel',$channal);
        }
        $shopData = $shop->pluck('create_time');
        if(isset($shopData)){
            return $this->getDataKeyValue($shopData, $type, $start, $end);
        }
        return [];
    }

    protected function getIncomeShop($type, $start, $end)
    {
        $shop = VersionOrder::leftJoin('shop','shop.hashid','version_order.shop_id')->whereBetween('success_time',[$start,$end])->where('type','permission')->select('shop.create_time as create_time');
        request('version') && $shop->where('shop.version',request('version'));
        $info = $shop->get();
        $mk = [];
        if($info){
            foreach ($info as $ke){
                $mk[] = $ke->create_time;
            }
        }
        return $this->getDataKeyValue($mk, $type, $start, $end);
    }

    protected function getActiveShop($type, $start, $end)
    {
        $shop = Shop::leftJoin('user_shop','shop.hashid','user_shop.shop_id')->where('user_shop.admin',1);
        request('version') && $shop->where('shop.version',request('version'));
        $user_ids = $shop->pluck('user_id');
        $info = Users::whereBetween('login_time',[$start,$end])
            ->whereIn('id',$user_ids)
            ->orderBy('created_at', 'asc')
            ->get();
        $mk = [];
        if($info){
            foreach ($info as $ke){
                $mk[] = $ke->login_time;
            }
        }
        return $this->getDataKeyValue($mk, $type, $start, $end);
    }

    /**
     * 用户增长统计
     *
     * @param $type
     * @return array
     */
    private function getUserTotal($type)
    {
        switch ($type) {
            case 1: //按天
                $key = 'manage:user:day';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getDayMonthData('Ymd', date('Y-m-d 00:00:00',strtotime("-60 days")));
                    Redis::setex($key,4*3600,json_encode($ret));
                }
                break;
            case 2: //按月
                $key = 'manage:user:month';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getDayMonthData('Ym', date('2017-05-01 00:00:00'));
                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            case 3: //按周
                $key = 'manage:user:week';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getDayMonthData('YW', date('Y-01-01 00:00:00'));
                    Redis::setex($key,0.5*86400,json_encode($ret));
                }
                break;
            default: //实时
                $key = 'manage:user:hour';
                if(!request('cache') && Redis::exists($key)){
                    $ret = json_decode(Redis::get($key),1);
                }else{
                    $ret = $this->getDayMonthData('YmdH', date('Y-m-d 00:00:00'));
                    Redis::setex($key,1800,json_encode($ret));
                }
                break;
        }
        return $ret;
    }

    private function getDayMonthData($type, $start = 0,$end = 0)
    {
        $end = $end ?: time();
        $registerUser = $this->getUserNumber($type, $start, $end);
        $incomeUser = $this->getIncomeUser($type, $start, $end);
        $activeUser = $this->getActiveUser($type, $start, $end);
        return ['registerUser' => $registerUser,'incomeUser' =>$incomeUser,'activeUser' => $activeUser];
    }

    /**
     * 注册用户
     * @param $type
     * @param $start
     * @param $end
     * @param int $sign
     * @param $begin
     * @return array
     */
    private function getUserNumber($type, $start, $end)
    {
        $end = hg_format_date($end);
        $shop = Shop::leftJoin('user_shop','shop.hashid','user_shop.shop_id');
        request('version') && $shop->where('shop.version',request('version'));
        $user_ids = $shop->pluck('user_id');
        $mk = Users::whereBetween('created_at', [$start, $end])
            ->whereIn('id',$user_ids)
            ->orderBy('created_at', 'asc')
            ->pluck('created_at');
        if(isset($mk)){
            return $this->getDataKeyValue($mk, $type, strtotime($start), strtotime($end));
        }
        return [];
    }

    /**
     * 付费用户
     * @param $type
     * @param $start
     * @param $end
     * @param int $sign
     * @param $begin
     * @return array
     */
    private function getIncomeUser($type, $start, $end){
        $stime = strtotime($start);
        $shop = VersionOrder::leftJoin('shop','shop.hashid','version_order.shop_id')->whereBetween('success_time',[$stime,$end])->select('shop.create_time as create_time');
        request('version') && $shop->where('shop.version',request('version'));
        $info = $shop->get();
        if($info){
            foreach ($info as $ke){
                $mk[] = date('Y-m-d H:i:s',$ke->create_time);
            }
            if(isset($mk)){
                return $this->getDataKeyValue($mk, $type, $stime, $end);
            }
        }
        return [];
    }

    /**
     * 活跃用户 暂时取登录时间是今天的用户
     * @param $type
     * @param $start
     * @param $end
     * @param int $sign
     * @param $begin
     * @return array
     */
    private function getActiveUser($type, $start, $end){
        $stime = strtotime($start);
        $shop = Shop::leftJoin('user_shop','shop.hashid','user_shop.shop_id');
        request('version') && $shop->where('shop.version',request('version'));
        $user_ids = $shop->pluck('user_id');
        $info = Users::whereBetween('login_time',[$stime,$end])
            ->whereIn('id',$user_ids)
            ->orderBy('created_at', 'asc')
            ->get();
        if($info){
            foreach ($info as $ke){
                $mk[] = date('Y-m-d H:i:s',$ke->login_time);
            }
            if(isset($mk)){
                return $this->getDataKeyValue($mk, $type, $stime, $end);
            }
        }
        return [];
    }

    private function getDataKeyValue($info, $type, $start, $end)
    {
        $keys = $values = $back = [];
        if (isset($info)) {
            foreach ($info as $item) {
                //$time = strtotime($item);
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
                }elseif($type == 'YW'){
                    $plus = "+1 week";
                    $formate = 'Y第W周';
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

    public function test()
    {
        if(request('start_time')){
            $startTime = request('start_time');
            $endTime = time();

            if($startTime >= $endTime){
                return $this->output(['success'=>1]);
            }

            $time = [];
            for($k = $startTime;$k < $endTime;$k=strtotime('+1 day',$k)){
                $time[] = [
                    'beginYesterday' => mktime(0,0,0,date('m',$k),date('d',$k)-1,date('Y',$k)),
                    'endYesterday' => mktime(0,0,0,date('m',$k),date('d',$k),date('Y',$k))-1,
                    'date' => date('Ymd',$k),
                ];
            }

            foreach($time as $timeValue){
                $flag = $timeValue['date'];
                if(request('type') == 'total'){
                    if(!Cache::has('cron'.$flag)){
                        $this->dispatch((new CronData($timeValue)));
                        Cache::forever('cron'.$flag,true);;
                    }
                }elseif(request('type') == 'content'){
                    if(0 && !Cache::has('content:cron'.$flag)){
                        $this->dispatch((new CronContentData($timeValue)));
                        Cache::forever('content:cron'.$flag,true);
                    }
                }
            }
        }
        return $this->output(['success'=>1]);
    }
}