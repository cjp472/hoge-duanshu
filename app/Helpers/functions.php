<?php

use App\Models\Shop;
use App\Models\VersionExpire;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;

/****
 * 时间处理
 */
if(!function_exists('hg_friendly_date')) {
    function hg_friendly_date($from)
    {
        static $now = NULL;
        $now == NULL && $now = time();
        ! is_numeric( $from ) && $from = strtotime( $from );
        $seconds = $now - $from;
        $minutes = floor( $seconds / 60 );
        $hours   = floor( $seconds / 3600 );
        $day     = round( ( strtotime( date( 'Y-m-d', $now ) ) - strtotime( date( 'Y-m-d', $from ) ) ) / 86400 );
        if( $seconds == 0 ){
            return '刚刚';
        }
        if( ( $seconds >= 0 ) && ( $seconds <= 60 ) ){
            return "{$seconds}秒前";
        }
        if( ( $minutes >= 0 ) && ( $minutes <= 60 ) ){
            return "{$minutes}分钟前";
        }
        if( ( $hours >= 0 ) && ( $hours <= 24 ) ){
            return "{$hours}小时前";
        }
        if( ( date( 'Y' ) - date( 'Y', $from ) ) > 0 ) {
            return date( 'Y-m-d', $from );
        }
        if($day>30 && $day < 365 ){
            $day = 31;
        }elseif ($day > 365){
            $day = 366;
        }
        switch( $day ){
            case 0:
                return date( '今天H:i', $from );
                break;

            case 1:
                return date( '昨天H:i', $from );
                break;

            case 31:
                return date( 'm-d', $from );
                break;

            case 366:
                return date( 'Y-m-d', $from );
                break;

            default :
                return "{$day}天前";
                break;
        }
    }
}

/**
 * 调试打印
 */
if(!function_exists('hg_debug')) {
    function hg_debug()
    {
        $vars = func_get_args();
        foreach ($vars as $var)
        {
            var_export($var);
        }
        exit;
    }
}

/**
 * 文件打印
 */
if(!function_exists('hg_fpc')){
    function hg_fpc($content)
    {
        file_put_contents(storage_path('logs/debug.txt'),date('Y-m-d H:i:s')."\n".var_export($content,1)."\n----------------------------\n",FILE_APPEND);
    }
}

/**
 * sha256加密
 */
if(!function_exists('hg_hash_sha256')){
    function hg_hash_sha256($param = [],$need_upcase = 1)
    {
        $string = '';
        foreach ($param as $k=>$v){
            $string .= $k.'='.$v.'&';
        }
        $string = trim($string,'&');
        $encode = hash('sha256', $string);
        if($need_upcase){
            return strtoupper($encode);
        }else{
            return $encode;
        }
    }
}

if(!function_exists('hg_verify_signature')){
    function hg_verify_signature($data = [],$timesTamp = '',$appId = '',$appSecret = '',$shop_id=''){
        $appId = $appId ?: config('define.order_center.app_id');
        $appSecret = $appSecret ?: config('define.order_center.app_secret');
        $timesTamp = $timesTamp ?: time();
        $param = [
            'access_key' => $appId,
            'access_secret' => $appSecret,
            'timestamp'     => $timesTamp,
        ];
        if($data) {
            $param['raw_data'] = json_encode($data);
        }
        $string = '';
        foreach ($param as $k=>$v){
            $string .= $k.'='.$v.'&';
        }
        $string = trim($string,'&');
        $sign = strtoupper(md5($string));
        $client = new \GuzzleHttp\Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-API-SIGNATURE' => $sign,
                'x-API-KEY' => $appId,
                'x-API-TIMESTAMP' => $timesTamp,
                'AUTHORIZATION' => $shop_id
            ],
            'body'  => $data ? json_encode($data) : '',
        ]);
        return $client;
    }
}

/**
 * 加密支付中心签名
 */
if(!function_exists('hg_pay_signature')){
    function hg_pay_signature($param = [],$timestamp = '',$appId = '',$appSecret = '')
    {
        $appId = $appId ?: config('define.pay.youzan.app_id');
        $appSecret = $appSecret ?: config('define.pay.youzan.app_secret');
        $timestamp = $timestamp ?: time();
        $param = [
            'access_key' => $appId,
            'access_secret' => $appSecret,
            'timestamp'     => $timestamp,
            'raw_data'      => $param,
        ];
        $sign = hg_hash_sha256($param);
        return [
            'access_key'    => $appId,
            'timestamp'     => $timestamp,
            'raw_data'      => $param,
            'sign'          => $sign,
        ];
    }
}
/**
 * 格式化店铺信息返回
 */
if(!function_exists('hg_shop_response')){
    function hg_shop_response($user_id , $update = false)
    {
        if($update || !session('shop:'.$user_id)){
            $shop = \App\Models\UserShop::userShop($user_id);
            if($shop && $shop->shop_id)
            {
                $message = intval($shop->message);
                if($message >= 50){
                    $messageStatus = 'enough';
                }elseif($message < 50 && $message > 0){
                    $messageStatus = 'less';
                }else{
                    $messageStatus = 'none';
                }
                $announce = \Illuminate\Support\Facades\Redis::hgetall('announce:'.$shop->shop_id);
                if($announce && is_array($announce)){
                    $announce = array_map(function($v){
                        return intval($v);
                    },$announce);
                }
                $shopResponse = [
                    'id'            => $shop->shop_id,
                    'version'       => $shop->version == 'unactive-partner' ? 'partner' : $shop->version,
                    'admin'         => $shop->admin ? 1 : 0,
                    'account_id'    => trim($shop->account_id),
                    'permission'    => $shop->permission ? unserialize($shop->permission) : [],
                    'applet_version'    => $shop->applet_version ? : 'basic',
                    'is_promotion'  => intval($shop->is_promotion),
                    'announce'          => $announce,//公告显示状态，1-显示，0-不显示
                    'sms_status'    => $messageStatus,
                    'is_obs'        => $shop->is_obs ? 1 : 0,
                    'is_online_live'=> $shop->is_online_live ? 1 : 0,
                    'is_applet_refund'        => $shop->is_applet_refund ? 1 : 0,
                ];
//                if($shop->version != 'partner') {
//                    $mobile = \App\Models\UserShop::where('shop_id',$shop->shop_id)->leftJoin('users','users.id','=','user_id')->pluck('mobile');
//                    $partnerApply = \App\Models\PartnerApply::whereIn('mobile', $mobile)->pluck('id');
//                    $shopResponse['partner_state'] = $partnerApply->isEmpty() ? 0 : 1;
//
//                }
            }else{
                $shopResponse = [];
            }
            session(['shop:'.$user_id => $shopResponse]);
        }else{
            $shopResponse = session('shop:'.$user_id);
        }
        $shop_id = $shopResponse['id'];
        $shop_data = Shop::where('hashid', $shop_id)->firstOrFail();
        $version_expire = VersionExpire::where(['hashid' => $shop_id, 'version'=>$shop_data->version, 'is_expire'=>0])->orderByDesc('expire')->first();
        $is_version_expire = $version_expire ? $version_expire->expire < time() : 1;
        $shopResponse['is_version_expire'] = $is_version_expire;
        return $shopResponse;
    }
}
/**
 * 格式化用户信息返回
 */
if(!function_exists('hg_user_response')){
    function hg_user_response()
    {
        if(Auth::id()){
            return [
                'id'        => Auth::id(),
                'name'      => Auth::user()->name,
                'avatar'    => Auth::user()->avatar ?: '',
                'active'    => Auth::user()->active ? 1 : 0,
                'duanshu_session'    => encrypt(request()->session()->getId())
            ];
        }
        return [];
    }
}

/**
 * 判断是否为手机号格式
 */
if(!function_exists('hg_check_is_mobile')){
    function hg_check_is_mobile($input = '')
    {
        if(!$input){
            return false;
        }
        $pattern = "/^(0|86|17951)?(13[0-9]|15[012356789]|1[789][0-9]|14[57])[0-9]{8}$/";
        if ( preg_match( $pattern, $input ) ){
            return true;
        }
        return false;
    }
}
/**
 * 判断是否为邮箱格式
 */
if(!function_exists('hg_check_is_email')){
    function hg_check_is_email($input = '')
    {
        if(!$input){
            return false;
        }
        $pattern = "/^.+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,4}|[0-9]{1,4})(\]?)$/";
        if ( preg_match( $pattern, $input ) ){
            return true;
        }
        return false;
    }
}

/**
 * 格式化时间戳
 * Y-m-d H:i:s
 */
if(!function_exists('hg_format_date')){
    function hg_format_date($date = '')
    {
        if ( ! $date)   return date('Y-m-d H:i:s');
        return date('Y-m-d H:i:s', $date);
    }
}

if(!function_exists('hg_getip')){
    function hg_getip() {
        global $_INPUT;
        if (isset($_INPUT['lpip'])) {
            if (hg_checkip($_INPUT['lpip'])) {
                return $_INPUT['lpip'];
            }
        }
        $realip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
            foreach ($matches[0] AS $realip) {
                if (!preg_match("#^(10|172\.16|192\.168)\.#", $realip)) { break; }
            }
        }
        elseif (isset($_SERVER['HTTP_FROM'])) {
            $realip = $_SERVER['HTTP_FROM'];
        }
        elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $realip = $_SERVER['HTTP_X_REAL_IP'];
        }
        elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $realip = $_SERVER['REMOTE_ADDR'];
        }
        if (!hg_checkip($realip)) {
            $realip = '';
        }
        return $realip;
    }
}

if(!function_exists('hg_checkip')){
    function hg_checkip ($ipaddres)
    {
        $preg="/\A((([0-9]?[0-9])|(1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))\.){3}(([0-9]?[0-9])|(1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))\Z/";
        if (preg_match($preg,$ipaddres)) {
            return true;
        }
        return false;
    }
}


/**
 * 链接分隔成数组（host_file）形式
 */
if(!function_exists('hg_explore_image_link')){
    function hg_explore_image_link($url='',$is_video=0){
        if(!$url){
            return '';
        }
        $file = '';
        $query = '';
        $match = '';
        if (is_string($url)) {
            $link_array = parse_url($url);
            preg_match('/qnimg.v2.dingdone.com/',$link_array['host'],$match);
            $file = $link_array['path'];
            $query = isset($link_array['query']) ? $link_array['query'] : '';
        }elseif (is_array($url) && isset($url['file']) && isset($url['host'])){
            preg_match('/qnimg.v2.dingdone.com/',$url['host'],$match);
            $file = $url['file'];
            $query = isset($url['query']) ? $url['query'] : '';
        }
        return serialize([
            'host' => $is_video ? VIDEO_IMAGE_HOST : ($match ? DINGDONE_IMAGE_HOST : IMAGE_HOST),
            'file' => $file,
            'query' => $query,
        ]);


    }
}


function simple_explore_image_link($url) {
    $a = parse_url($url);
    $b = [
        'host'=>$a['scheme'].$a['host'],
        'file'=>$a['file'],
        'query'=>''
    ];
    return $b;
}


/**
 * 反序列化处理图片链接（兼容老数据）
 */
if(!function_exists('hg_unserialize_image_link')){
    function hg_unserialize_image_link($image,$is_video=0)
    {
        if (!$image) {
            return [];
        }
        if (is_string($image)) {
            $file =$match = '';
            $query = '';
            $match = '';
            try {
                $image_array = unserialize($image);
                $file = $image_array['file'];
                preg_match('/qnimg.v2.dingdone.com/',$image_array['host'],$match);
                $query = isset($image_array['query']) ?$image_array['query'] : '';
            } catch (Exception $exception) {
                if (preg_match('/http[s]?:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is', $image)) {
                    $link_array = parse_url($image);
                    preg_match('/qnimg.v2.dingdone.com/',$link_array['host'],$match);
                    $file = $link_array['path'];
                    $query = isset($link_array['query']) ? $link_array['query'] : '';
                }
            }
            return [
                'host'  => $is_video ? VIDEO_IMAGE_HOST :($match ? DINGDONE_IMAGE_HOST : IMAGE_HOST),
                'file'  => $file,
                'query' => $query,
            ];
        }
        return [];
    }
}

/**
 * 解析图片链接
 */
if(!function_exists('hg_parse_image_link')){
    function hg_parse_image_link($image){
        if($image){
            if(is_serialized($image)){
                $image_array = unserialize($image);
                $link = isset($image_array['host']) ? $image_array['host'] : '';
                $link .= isset($image_array['file']) ? $image_array['file'] : '';
            }else{
                $link = $image;
            }
            return $link;
        }
        return '';
    }
}

/**
 * 是否序列化
 */
if(!function_exists('is_serialized')) {
    function is_serialized($data)
    {
        $data = trim($data);
        if ('N;' == $data)
            return true;
        if (!preg_match('/^([adObis]):/', $data, $badions))
            return false;
        switch ($badions[1]) {
            case 'a' :
            case 'O' :
            case 's' :
                if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data))
                    return true;
                break;
            case 'b' :
            case 'i' :
            case 'd' :
                if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data))
                    return true;
                break;
        }
        return false;
    }
}


/**
 * sha1加密，短书app使用
 */
if(!function_exists('hg_hash_sha1')){
    function hg_hash_sha1($data=[],$appKey = '',$appSecret = '',$timestamp='',$version='1.0')
    {
        $appKey = $appKey ?: config('define.dingdone.key');
        $appSecret = $appSecret ?: config('define.dingdone.secret');
        $timestamp = $timestamp ?: time();
        $string = $appKey.'&'.$appSecret.'&'.$version.'&'.$timestamp;
        $sign = sha1($string);
        $client = new \GuzzleHttp\Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-SIGNATURE' => $sign,
                'X-API-KEY' => $appKey,
                'X-API-TIMESTAMP' => $timestamp,
                'X-API-VERSION' => $version,
            ],
            'body'  => $data ? json_encode($data) : '',
        ]);
        return $client;
    }
}


/**
 * 使用FFmpeg获取音频时长
 */
if(!function_exists('hg_get_file_time')){
    function hg_get_file_time($file='',$is_string=0)
    {
        if (!$file) {
            return 0;
        }

        try {
            $commend = ' /usr/local/ffmpeg/bin/ffmpeg -i '.$file.' 2>&1';
            exec($commend, $arr, $state);
            preg_match('/\d{2}:\d{2}:\d{2}/',  implode($arr), $match);
            if(isset($match[0])){
                if(!$is_string) {
                    $time_array = explode(':', $match[0]);
                    return $time_array[0] * 3600 + $time_array[1] * 60 + $time_array[2];
                }else{
                    return $match[0];
                }
            }

        }catch (Exception $exception){
            return 0;
        }
        return 0;

    }
}

/**
 * 判断设备信息
 */
if(!function_exists('hg_get_agent')) {
    function hg_get_agent($agent='')
    {
       if (strpos($agent, 'iPhone')||strpos($agent, 'iPad')) {
            $browser = 'IOS';
        } elseif (strpos($agent, 'Android')) {
            $browser = 'Android';
        } else {
            $browser = 'WAP';
        }
        return $browser;
    }
}


/**
 * 获取文件大小
 */
if(!function_exists('hg_get_file_size')){
    function hg_get_file_size($file='')
    {
        if (!$file) {
            return 0;
        }

        try {
            $file_data = (new \GuzzleHttp\Client())->request('get',$file)->getBody()->getContents();
            $size = floatval(strlen($file_data) / 1024);
            return $size;
        }catch (Exception $exception){
            return 0;
        }
    }
}

if (!function_exists('applet_tag_increment')) {
    function applet_tag_increment($val, $max = 10)
    {
        $num = explode('.', $val);
        for ($i = count($num) - 1; $i >= 0; $i--) {
            ++$num[$i];
            if ($i > 0 && $num[$i] >= $max) {
                $num[$i] = 0;
            } else {
                break;
            }
        }
        return implode($num, '.');
    };
}
if (!function_exists('hg_analysis_sql_formate')) {
    function hg_analysis_sql_formate($type)
    {
        switch ($type){
            case 'Ym':
                $formate = '%Y%m';break;
            case 'Ymd':
                $formate = '%Y%m%d';break;
            case 'YmdH':
                $formate = '%Y%m%d%H';break;
            case 'YW':
                $formate = '%Y%u';break;
            default:
                $formate = '%Y%m%d %H:%i:%s';break;
        }
        return $formate;
    }
}

/**
 * 外部图片上传到腾讯云cos
 */
if(!function_exists('image_to_cos')) {
    function image_to_cos($url, $is_array = 0)
    {
        $file_name = md5($url . time()) . '.jpg';
        $content = (new \GuzzleHttp\Client())->request('get', $url)->getBody()->getContents();
        $upload_path = resource_path('material/admin/') . $file_name;
        file_put_contents($upload_path, $content);
        \qcloudcos\Cosapi::setRegion(config('qcloud.region'));
        $cos_path = config('qcloud.folder') . '/image/' . $file_name;
        $data = \qcloudcos\Cosapi::upload(config('qcloud.cos.bucket'), $upload_path, $cos_path);
        if($data['code']){
            $response = new \Illuminate\Http\Response([
                'error'     => $data['code'],
                'message'   => $data['message'],
            ], 200);
            throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
        }
        unlink($upload_path);
        return $is_array ? hg_unserialize_image_link($data['data']['source_url']) : $data['data']['source_url'];
    }
}

if (!function_exists('hg_analysis_formate')) {
    function hg_analysis_formate($info, $type, $start, $end)
    {
        $keys = $values = [];
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
                $values[] = isset($info[$date2]) ? $info[$date2] : 0;
            }
        }
        return ['keys' => $keys, 'values' => $values];
    }
}
if (!function_exists('hg_caculate_multiple')) {
    function hg_caculate_multiple($data,$key,$shop_id){
        if(\Illuminate\Support\Facades\Redis::exists('multiple:'.$shop_id)){
            $multiple = json_decode(\Illuminate\Support\Facades\Redis::get('multiple:'.$shop_id));
        }else{
            $multiple = \App\Models\Manage\ShopMultiple::where('shop_id',$shop_id)->first();
            if($multiple)
            {
                \Illuminate\Support\Facades\Redis::setex('multiple:'.$shop_id, 1800 ,json_encode($multiple));
            }
        }
        $range = $multiple && $multiple->range ? unserialize($multiple->range) : [];
        /******兼容老数据*******/
        if($multiple && $multiple->multiple && is_numeric($multiple->multiple)){
            $ke = ['view' => 1,'subscribe' => 2, 'online' => 3];
            if(in_array($ke[$key],$range)){
                return $data*$multiple->multiple;
            }else{
                return $data;
            }
        }
        /******兼容老数据*******/
        if($multiple && isset($range[$key]) && $range[$key]){
            $mul = $multiple && $multiple->multiple ? unserialize($multiple->multiple) : [];
            $base = $multiple && $multiple->base  ? unserialize($multiple->base):[];        $multi_Value = isset($mul[$key]) ? $mul[$key] : 1;
            $base_value = isset($base[$key]) ? $base[$key] : 1;
            return ceil($data*$multi_Value + $base_value);
        }else{
            return $data;
        }
        return $data;
    }
}

if (!function_exists('hg_search_type')) {
    function hg_search_type($data){
        if(strlen($data) <= 11 && preg_match("/^1[34578]{1}\d*$/",$data)){
            return 'mobile';
        }elseif(strlen($data) <= 18 && preg_match('/^wx\w+$/',$data)){
            return 'appid';
        }elseif(strlen($data) <= 18 && preg_match('/^\w+$/',$data)){
            return 'shop_id';
        }else{
            return 'name';
        }
    }
}

if (!function_exists('hg_is_same_member')) {
    function hg_is_same_member($member_id,$shop_id){
        $mobile = \App\Models\Member::where('uid',$member_id)->value('mobile');
        if($mobile){
            $member_ids = \App\Models\Member::where(['shop_id'=>$shop_id,'mobile'=>$mobile])->pluck('uid');
            $member = collect($member_ids)->toArray();
            return $member;
        }
        return [$member_id];
    }
}

/**
 * 排序封住
 * @param array $data 排序前数据
 * @param int $sort_id 要排序的内容id
 * @param int $order  要排的顺序
 * @param int $old_order  内容之前的顺序
 * @param string $type  排序的内容类型
 * @return bool
 */
if (!function_exists('hg_sort')) {
    function hg_sort($data=[],$sort_id=0,$order=0,$old_order=0,$type=''){
        if(is_object($data)){
            $data = $data->toArray();
        }
        $info = array_flip($data);
        $old_order = isset($info[$sort_id]) ? $info[$sort_id] + 1 : 0;

        if($old_order != $order) {
            foreach ($data as $key => $id) {
                $key++;
                switch ($order) {
                    //放首位
                    case 0:
                        if ($key <= $old_order) {
                            $key += 1;
                        }
                        if ($id == $sort_id) {
                            $key = 1;
                        }
                        break;
                    //放末尾
                    case -1:
                        if ($old_order < $key) {
                            $key -= 1;
                        }
                        //排序的id=当前id
                        if ($sort_id == $id) {
                            //如果超出范围，用范围最大值
                            $key = count($data);
                        }
                        break;
                    default :
                        //大=>小
                        if ($old_order > $order) {
                            if ($key >= $order && $key <= $old_order) {
                                $key += 1;
                            }
                        } //小=>大
                        elseif ($old_order < $order) {
                            if ($key <= $order && $key >= $old_order) {
                                $key -= 1;
                            }
                        }
                        //排序的id=当前id
                        if ($sort_id == $id) {
                            //如果超出范围，用范围最大值
                            $key = count($data) < $order ? count($data) : $order;
                        }
                        break;
                }
                switch ($type){
                    case 'banner':
                        \App\Models\Banner::where(['id'=>$id])->update(['order_id' => $key]);
                        break;
                    case 'column':
                        \App\Models\Column::where(['hashid'=>$id])->update(['order_id' => $key]);
                        break;
                    case 'column_content':
                        \App\Models\Content::where(['hashid'=>$id])->whereIn('type',['article','audio','video','live'])->update(['column_order_id' => $key]);
                        break;
                    case 'course':
                        \App\Models\Course::where(['hashid'=>$id])->update(['order_id' => $key]);
                        break;
                    case 'class':
                        \App\Models\ClassContent::where(['id'=>$id])->update(['order_id' => $key]);
                        break;
                    case 'chapter':
                        \App\Models\ChapterContent::where(['id'=>$id])->update(['order_id' => $key]);
                        break;
                    case 'memberCard':
                        \App\Models\MemberCard::where(['hashid'=>$id])->update(['order_id' => $key]);
                        break;
                    case 'limit_purchase':
                        \App\Models\LimitPurchase::where(['hashid'=>$id])->update(['order_id' => $key]);
                        break;
                    case 'navigation':
                        \App\Models\Navigation::where(['id'=>$id])->update(['order_id' => $key]);
                        break;
                    case 'contentType':
                        \App\Models\ContentType::where(['id'=>$id])->update(['order_id' => $key]);
                        break;
                    case 'type':
                        \App\Models\Type::where(['id'=>$id])->update(['order_id' => $key]);
                        break;
                    default:
                        \App\Models\Content::where(['hashid'=>$id,'type'=>$type])->update(['order_id' => $key]);
                        break;

                }
            }
        }
        return true;
    }
}




/**
 * 通用内容排序
 * 要求排序字段为decimal类型且有一个小数位
 *
 * @param [type] $table
 * @param [type] $filed
 * @param [type] $order
 * @return void
 */
function hg_content_sort($table, $field, $filter, $orderBy, $id, $order)
{
    $base = DB::table($table)->where($filter);
    $countSql = clone $base;
    $count = $countSql->count();

    DB::statement('SET @row_number = 0;');
    
    $updateSubSql = clone $base;
    $updateSubSql->select('id', DB::raw('(@row_number:=@row_number + 1) AS num'));
    $updateSubSql->where('id', '!=', $id);
    foreach ($orderBy as $i) {
        $updateSubSql->orderBy($i[0], $i[1]);
    }
    
    $sql = $updateSubSql->toSql();
    
    $filterValues = [];
    foreach ($filter as $i) {
        $filterValues[] = $i[2];
    }
    $filterValues[] = $id;
    DB::update("UPDATE hg_$table
                  INNER JOIN
                  ($sql) AS o
                  ON
                  hg_$table.id = o.id
                  SET hg_$table.`$field` = o.num;", $filterValues);
    $update = [];
    if ($order < $count) {
        $update[$field] = $order >= 1 ? $order - 0.5:0;
    } else {
        $update[$field] = $count;
    }
    DB::table($table)->where($filter)->where('id', $id)->update($update);
    return;
}



/**
 * 退款逻辑封装
 */
if(!function_exists('hg_member_refunds')){
    function hg_member_refunds($params=[]){

        $client = hg_verify_signature($params);
        $url = config('define.order_center.api.member_order_refunds');
        try{
            $res = $client->request('POST',$url);
            $return = $res->getBody()->getContents();
            event(new \App\Events\CurlLogsEvent($return,$client,$url));
        }catch (\Exception $exception){
            event(new \App\Events\ErrorHandle($exception,'order_center'));
            $response = new \Illuminate\Http\Response([
                'error'     => 'order-refunds-error',
                'message'   => trans('validation.order-refunds-error'),
            ], 200);
            throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
        }
        return $return;
    }
}


/**
 * 小程序退款申请
 */
if(!function_exists('hg_applet_refunds')){
    function hg_applet_refunds($param=[],$refund_type=''){

        $config = config('wechat');
        $certificate_path = base_path('certificate/'.$param['shop_id']);
        $applet = \App\Models\AppletUpgrade::where('shop_id',$param['shop_id'])->first();
        $applet && $config['app_id'] = $applet->appid;
        $applet && $config['payment']['merchant_id'] = $applet->mchid;
        $applet && $config['payment']['key'] = $applet->api_key;
        //小程序证书路径配置
        $config['payment']['cert_path'] = $certificate_path.'/apiclient_cert.pem';
        $config['payment']['key_path'] = $certificate_path.'/apiclient_key.pem';
        $response = [];
        try {
            $app = new \EasyWeChat\Foundation\Application($config);
            switch ($refund_type) {
                case 'transaction_id':
                    $return = $app->payment->refundByTransactionId($param['order_no'], $param['refund_no'], $param['total_fee'], $param['refund_fee'], $param['mch_id']);
                    break;
                case 'out_trade_no':
                    $return = $app->payment->refund($param['order_no'], $param['refund_no'], $param['total_fee'], $param['refund_fee'], $param['mch_id']);
                    break;
                default:
                    $return = $app->payment->refund($param['order_no'], $param['refund_no'], $param['total_fee'], $param['refund_fee'], $param['mch_id']);
                    break;
            }
            $response = $return->all();
            event(new \App\Events\CurlLogsEvent(json_encode($response),new \GuzzleHttp\Client(['body'=>json_encode($param)]),'https://api.mch.weixin.qq.com/secapi/pay/refund'));
        }catch (Exception $exception){
            event(new \App\Events\ErrorHandle($exception));
        }
        return $response;
    }

}
/**
 * 检测推广员是否存在
 */
if(!function_exists('hg_check_promotion')){
    function hg_check_promotion($promotion_id,$shop_id){
        //查看这个推广用户是不是推广员
        $promoterStatus = \App\Models\Promotion::select('is_delete', 'state','promotion_id','visit_id')->where(['shop_id' => $shop_id, 'promotion_id' => $promotion_id,'is_delete'=>0,'state'=>1])->first();
        if(!$promoterStatus){
            $member_ids = hg_is_same_member($promotion_id,$shop_id);
            $promoterStatus = \App\Models\Promotion::select('is_delete', 'state','promotion_id','visit_id')->where(['shop_id' => $shop_id,'is_delete'=>0,'state'=>1])->whereIn( 'promotion_id',$member_ids)->first();
        }
        return $promoterStatus;
    }
}

if (!function_exists('hg_sec_to_time')) {
    function hg_sec_to_time($times)
    {
        $result = '00:00';
        if ($times > 0) {
            $hour = str_pad(floor($times / 3600),2,0,STR_PAD_LEFT);
            $minute = str_pad(floor(($times - 3600 * $hour) / 60),2,0,STR_PAD_LEFT);
            $second = str_pad(floor((($times - 3600 * $hour) - 60 * $minute) % 60),2,0,STR_PAD_LEFT);
            if($hour != '00'){
                $result = $hour . ':' . $minute . ':' . $second;
            }else{
                $result =  $minute . ':' . $second;
            }
        }
        return $result;
    }
}


if ( ! function_exists('uuid'))
{
    function uuid()
    {
        $uuid = Uuid::uuid1()->toString();
        return str_replace('-', '', $uuid);
    }
}

if ( ! function_exists('hg_check_marketing'))
{
    function hg_check_marketing($shop_id,$type)
    {
        $ids = \App\Models\MarketingActivity::where(['shop_id'=>$shop_id,'content_type'=>$type])
            ->where(function ($query) {
                $query->where('end_time', 0)->orWhere('end_time','>',time());
            })
            ->pluck('content_id')->toArray();
        return $ids?:[];
    }
}
/**
 * 小程序预下单请求
 */
if(!function_exists('hg_applet_undefined_order')){
    function hg_applet_undefined_order($orders,$shop_id,$openid)
    {

        //使用easywechat插件
        $options = config('wechat');
        $applet = \App\Models\AppletUpgrade::where('shop_id', $shop_id)->first();
        $applet && $options['app_id'] = $applet->appid;
        $applet && $options['payment']['merchant_id'] = $applet->mchid;
        $applet && $options['payment']['key'] = $applet->api_key;
        $app = new \EasyWeChat\Foundation\Application($options);
        $payment = $app->payment;
        $attributes = [
            'trade_type' => 'JSAPI', // JSAPI，NATIVE，APP...
            'body' => $orders->content_type == 'column' ? '订阅:' . $orders->content_title : '购买:' . $orders->content_title,
            'detail' => $orders->content_type == 'column' ? '订阅:' . $orders->content_title : '购买:' . $orders->content_title,
            'out_trade_no' => $orders->center_order_no,
            'total_fee' => $orders->price ? $orders->price * 100 : 0,
            'notify_url' => config('define.pay.applet.notify_url'), // 支付结果通知网址
            'openid' => $openid,
            'spbill_create_ip' => \EasyWeChat\Payment\get_client_ip(),
            'attach' => json_encode(['shop_id' => $shop_id]),
        ];
        $order = new \EasyWeChat\Payment\Order($attributes);

        $result = $payment->prepare($order);
        if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS') {
            $response = ['return' => $result, 'key' => $applet->api_key];
            return $response;
        } else {
            event(new \App\Events\CurlLogsEvent($result->toJson(), new \GuzzleHttp\Client(['body' => $order ? $order->toJson() : '',]), 'https://api.mch.weixin.qq.com/pay/unifiedorder'));
            //支付错误短书统一处理
            $response = new \Illuminate\Http\Response([
                'error'     => 'applet-pay-error',
                'message'   => trans('validation.applet-pay-error'),
            ], 200);
            throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
        }
    }
}

/**
 * 可自定义非必要参数的小程序预下单
 * $order ["body"=>"","detail"=>"","out_trade_no"=>"","price"=>"","attach"=>[],"notify_url"=>""]
 */
if (!function_exists('hg_applet_wx_pre_order')) {
    function hg_applet_wx_pre_order($shop_id,$openid,$order)
    {
        $options = config('wechat');
        $applet = \App\Models\AppletUpgrade::where('shop_id', $shop_id)->first();
        if(is_null($applet)){
            return ['error'=>'applet-pay-config-not-found'];
        }

        $options['app_id'] = $applet->appid;
        $options['payment']['merchant_id'] = $applet->mchid;
        $options['payment']['key'] = $applet->api_key;
        
        $app = new \EasyWeChat\Foundation\Application($options);
        $payment = $app->payment;
        
        $attributes = [
            'trade_type' => 'JSAPI', // JSAPI，NATIVE，APP...
            'body' => $order['body'],
            'detail' =>$order['detail'],
            'out_trade_no' => $order['out_trade_no'],
            'total_fee' => $order['price'] * 100, //转为分
            'notify_url' => array_key_exists('notify_url',$order) ? $order['notify_url'] : config('define.pay.applet.notify_url'), // 支付结果通知网址
            'openid' => $openid,
            'spbill_create_ip' => \EasyWeChat\Payment\get_client_ip(),
            'attach' => json_encode(array_merge($order['attach'],['shop_id'=> $shop_id])),
        ];

        $order = new \EasyWeChat\Payment\Order($attributes);
        $result = $payment->prepare($order);
        if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS') {
            $response = ['return' => $result, 'key' => $applet->api_key];
            return $response;
        } else {
            event(new \App\Events\CurlLogsEvent($result->toJson(), new \GuzzleHttp\Client(['body' => $order ? $order->toJson() : '',]), 'https://api.mch.weixin.qq.com/pay/unifiedorder'));
            //支付错误短书统一处理
            return ['error'=>'applet-pay-error'];
        }
    }
}


if(!function_exists('onlyFreeContent')) {
    function onlyFreeContent() {
        return trim(request('source')) == 'wx_applet' && request('version') == 'basic' ? true : false;
    }
}

//处理昵称中的emoji表情
if(!function_exists('hg_emoji_encode')) {
    function hg_emoji_encode($nickname)
    {
        $strEncode = '';
        $length = mb_strlen($nickname, 'utf-8');
        for ($i = 0; $i < $length; $i++) {
            $_tmpStr = mb_substr($nickname, $i, 1, 'utf-8');
            if (strlen($_tmpStr) >= 4) {
                $strEncode .= '[[EMOJI:' . rawurlencode($_tmpStr) . ']]';
            } else {
                $strEncode .= $_tmpStr;
            }
        }
        return $strEncode;
    }
}

//校验价格
if(!function_exists('validate_price')) {
    function validate_price($price)
    {   
        $s = strval($price);
        $pattern = '/^[1-9]\d*(\.\d{1,2})?$|^0(\.\d{1,2})?$/';
        return boolval(preg_match($pattern, $s));
    }
}

//校验价格带最大价格限制
if(!function_exists('validate_price_with_max')) {
    function validate_price_with_max($price)
    {   
        if (validate_price($price) && $price <= MAX_ORDER_PRICE) {
            return True;
        } else {
            return False;
        }
    }
}

//折扣价格
if(!function_exists('hg_discount_price')) {
    function hg_discount_price($price,$discount_rate)
    {
        $discount_price = $price * $discount_rate; //折扣价格
        if( $discount_price <= 0 ) {
            $discount_price = 0.00 ;
        } else if ( $discount_price > $price ) {
            $discount_price = $price;
        } else if ( $discount_price > 0 && $discount_price < 0.01 ) {
            $discount_price = 0.01;
        } 
        return sprintf("%.2f",$discount_price);
    }
}

if(!function_exists('content_market_activities')) {
    function content_market_activities($shopId, $contentType, $contentId) {
        $ac = \App\Models\MarketingActivity::activiting($shopId)->where(['content_type'=>$contentType, 'content_id'=>$contentId])->pluck('marketing_type')->unique();
        return $ac->toArray();
    }
}

if(!function_exists('content_is_join_any_market_activity')) {
    function content_is_join_any_market_activity($shopId, $contentType, $contentId, $activities) {
        $ac = \App\Models\MarketingActivity::activiting($shopId)->where(['content_type'=>$contentType, 'content_id'=>$contentId])->pluck('marketing_type')->unique();
        $i = array_intersect($activities, $ac->toArray());
        return boolVal($i);
    }
}

/**
 * 查询内容参与的营销活动
 * @param str $shopId 店铺id
 * @param array $contents_type_id 内容类型-id ["article-724k9163knjl","course-724k9163knjl"]
 * @param array  $activities ["limit_purchase","fight_group","promotion"]
 * @return array [
 *                  "article-724k9163knjl" => ["limit_purchase","fight_group","promotion"],
 *                  "course-724k9163knjl" => ["limit_purchase","fight_group","promotion"]
 *               ]
 */
if(!function_exists('contents_market_activity')) {
    function contents_market_activity($shopId, $contents_type_id) {
        $query = \App\Models\MarketingActivity::activiting($shopId)
            ->select(DB::raw("id, CONCAT(content_type,'-',content_id) as content, marketing_type"))
            ->whereIn(DB::raw("CONCAT(content_type,'-',content_id)"),$contents_type_id)
            ->get();
        
        $contents_activity = [];
        foreach ($contents_type_id as $content) {
            $contents_activity[$content] = [];
            foreach ($query as $q) {
                if ($q->content == $content) {
                    $contents_activity[$content][] = $q->marketing_type;
                }
            }
        }
        return $contents_activity;
    }
}

if(!function_exists('is_any_contents_join_common_market_activity')) {
    function is_any_contents_join_common_market_activity($shopId, $contents_type_id) {
        $query = \App\Models\MarketingActivity::activiting($shopId)
            ->select(DB::raw("id, CONCAT(content_type,'-',content_id) as content, marketing_type"))
            ->whereIn(DB::raw("CONCAT(content_type,'-',content_id)"),$contents_type_id)
            ->whereIn('marketing_type', \App\Models\MarketingActivity::COMMON_ACTIVITY)
            ->get();
        
        $contents_activity = [];
        foreach ($contents_type_id as $content) {
            $contents_activity[$content] = [];
            foreach ($query as $q) {
                if ($q->content == $content) {
                    $contents_activity[$content][] = $q->marketing_type;
                }
            }
        }
        $contents_ac_values = array_values($contents_activity);
        foreach ($contents_ac_values as $v){
            if($v){
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('uuid4')) {
    function uuid4()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s%s%s%s%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('get_random_string')) {
    function get_random_string($len, $chars=null)
    {
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyz";
        }
        mt_srand(10000000*(double)microtime());
        for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }
}
