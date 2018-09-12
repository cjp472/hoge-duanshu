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
use App\Models\ShopDisable;
use App\Models\ShopInfo;
use App\Models\UserButtonClicks;
use App\Models\UserShop;
use App\Models\VersionExpire;
use App\Models\WebsiteSituation;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use qcloudcos\Cosapi;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class WebsiteController extends BaseController
{
    /**
     * 查看店铺信息
     */
    public function index()
    {
        $shop = Shop::select('hashid','title','brief', 'h5_host', 'version', 'applet_version','fast_login','is_applet_refund', 'applet_ios_pay', 'enable_customer_service')
            ->where('hashid', $this->shop['id'])
            ->firstOrFail();
        $shop->color = ShopColor::shopColor($this->shop['id']);

        return $this->output($this->getResponse($shop));
    }

    public function update()
    {
        $this->validateWithAttribute(['title'=> 'required | string |max:128'],['title'=>'标题']);

        $shop = Shop::where('hashid', $this->shop['id'])->first();
        $shop->title = request('title');
        $shop->saveOrFail();
        Cache::forget('share:'.$this->shop['id']);  //清除cahce里面店铺分析信息
        Cache::forget('shop:'.$this->shop['id']);
        return $this->output($this->getResponse($shop));
    }

    private function getResponse($shop){
        $h5_url = H5_DOMAIN.'/'.$shop->hashid.'/';
        return [
            'id'            => $shop->hashid,
            'title'         => $shop->title,
            'brief'         => $shop->brief,
            'url'           => $h5_url,
            'color'         => $shop->color ? : [],
            'version'       => $shop->version,
            'applet_version'    => $shop->applet_version ? : 'basic',
            'fast_login'    => intval($shop->fast_login),
            'is_applet_refund'  => intval($shop->is_applet_refund) ? 1 : 0,
            'h5_url'   => $h5_url,
            'h5_host'   => $shop->h5_host,
            'applet_ios_pay' => intval($shop->applet_ios_pay) ? 1 : 0,
            //基础版或者高级版才可以配置小程序客服
            'enable_customer_service' => ($this->shop['version'] == VERSION_ADVANCED || $this->shop['version'] == VERSION_STANDARD) && intval($shop->enable_customer_service) ? 1 : 0
        ];
    }

    private function h5Qrcode($h5_url){
        $file_name = md5($h5_url).'.png';
        $qrcode_path = resource_path('material/card/qrcode/'.$file_name);
        $cos_path = config('qcloud.folder').'/card/'.$file_name;
        QrCode::format('png')->size(100)->margin(0)->generate($h5_url, $qrcode_path);
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$qrcode_path,$cos_path,null,null,'insertOnly');
        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        file_exists($qrcode_path) && unlink($qrcode_path);
        return $data['data']['source_url'];
    }


    public function version(){
        $shop_id = $this->shop['id'];
        $shop = Shop::where(['hashid' => $shop_id])->first();
        if ($shop->version == 'partner' || $shop->version == 'unactive-partner') {
            return $this->output([
                'version' => $shop->version,
            ]);
        } else {
            $version_expire = VersionExpire::where(['hashid' => $shop->hashid, 'version'=>$shop->version, 'is_expire'=>0])->orderByDesc('expire')->first();
            $is_verify_expire = $shop->verify_expire < time();
            $is_version_expire = $version_expire ? $version_expire->expire < time() : 1;
            $is_probation = 0;
            $remain_days = 0;
            if (!$is_version_expire) {
                if ($shop->version == VERSION_BASIC) {
                    $is_probation = $shop->verify_expire > 0;
                } else {
                    $is_probation = $version_expire->method == 0;
                }
                $expire_date_time = strtotime(date('Y-m-d', $version_expire->expire));
                $date_time = strtotime(date('Y-m-d'));
                $remain_days = intval(($expire_date_time - $date_time) / 86400);
            }
            $result = [
                'version' => $shop->version,
                'expire_time' => $version_expire ? hg_format_date($version_expire->expire) : '',
                'is_version_expire' => $is_version_expire,
                'verify_expire_time' => $shop->verify_expire ? hg_format_date($shop->verify_expire) : '',
                'verify_type' => $shop->verify_first_type,
                'is_verify_expire' => $is_verify_expire,
                'verify_status' => $shop->verify_status,
                'is_probation' => $is_probation,
                'remain_days' => $remain_days,
            ];
            return $this->output($result);
        }
    }

    /**
     * 获取合伙人版本
     * @return int
     */
    private function getPartner(){
        $shop = Shop::where(['hashid'=>$this->shop['id']])->first();
        if(($shop->version == 'partner') || ($shop->version == 'unactive-partner')){
            return $shop;
        }
        return false;
    }

    /**
     * 获取订单信息
     */
    private function getOrder(){
        $data = $this->organize_data();//接收参数
        $client = $this->initClient($data);//生成签名
        $url = config('define.service_store.api.order_list');
        $res = $client->request('get',$url,['query'=>$data]);

        $data = json_decode($res->getBody()->getContents());
        event(new CurlLogsEvent(json_encode($data),$client,$url));
        if($res->getStatusCode() !== 200){
            return [];
        }
        if($res && $data->error_code){
            return [];
        }
        return $data;
    }

    /**
     * @return array
     */
    private function organize_data(){
        $arr=[
            'order_status' => 'unconfirm',
            'platform'=>'duanshu',
            'order_type'=> 'online',
            'product_type'=>'permission',
            'user_id'=>$this->shop['id']
        ];
        return $arr;

    }

    private function initClient($data){
        $time = time();
        $sign_array = [
            'access_key' => config('define.service_store.app_id'),
            'access_secret' => config('define.service_store.app_secret'),
            'timestamp'     => $time,
        ];
        $sign_string = urldecode(http_build_query($sign_array));
        $sign = strtoupper(hash('md5',$sign_string));
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-API-KEY' => config('define.service_store.app_id'),
                'x-API-TIMESTAMP' => $time,
                'x-API-SIGNATURE' => $sign,
                'AUTHORIZATION'   => $this->shop['id'],
            ],
        ]);
        return $client;
    }


    /**
     * @return mixed
     * 认证详情
     */
    public function verifyDetail(){
        $data = ['uid'=>$this->shop['id']];
        $client = hg_verify_signature($data,'','','',$this->shop['id']); //初始化 client
        $url = config('define.order_center.api.verify_detail');

        $res = $client->request('GET',$url,['query'=>$data]);
        $data = json_decode($res->getBody()->getContents(),1);
        event(new CurlLogsEvent(json_encode($data),$client,$url));
        if($res->getStatusCode() !== 200){
            $this->error('error-sync-order');
        }
        if($res && $data['error_code'] && $data['error_code']!=6109){
            $this->errorWithText(
                'error-sync-order-'.$data['error_code'],
                $data['error_message']
            );
        }elseif($data['error_code']==6109){
            return $this->output(['status'=>'none']);
        }
        if($data['result'] && $data['result']['status']!='none' ) {
            $data['result']['create_time'] = isset($data['result']['create_time']) ? hg_format_date($data['result']['create_time']) : '';
            $data['result']['update_time'] = isset($data['result']['update_time']) ? hg_format_date($data['result']['update_time']) : '';
        }
        return $this->output($data['result']);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 更新数据库认证状态
     */
    public function verifyStatus(){
        $data = ['uid'=>$this->shop['id']];
        $client = hg_verify_signature($data,'','','',$this->shop['id']); //初始化 client
        $url = config('define.order_center.api.verify_detail');

        $res = $client->request('GET',$url,['query'=>$data]);
        $data = json_decode($res->getBody()->getContents(),1);
        event(new CurlLogsEvent(json_encode($data),$client,$url));
        if($res->getStatusCode() !== 200){
            $this->error('error-sync-order');
        }
        if($res && $data['error_code']){
            $this->errorWithText(
                'error-sync-order-'.$data['error_code'],
                $data['error_message']
            );
        }
        if($data['result'] && $data['result']['status']!='none' ) {
            Shop::where('hashid',$this->shop['id'])->update(['verify_status'=>$data['result']['status'],'verify_first_type'=>$data['result']['verify_first_type'],'withdraw_account'=>'deactivate']);
        }
        return $this->output(['success'=>1]);
    }


    /**
     * 认证回调
     * @return array
     */
    public function verifyCallback()
    {
        $data = request()->input();
        if($data && $data['data']){
            $param = $data['data'];
            Shop::where('hashid',$param['user']['uid'])
                ->update([
                    'verify_status'=>$param['status'],
                    'verify_first_type'=>$param['verify_first_type'],
                ]);
            //认证系统消息
            if($param['status'] == 'success'){
//                Redis::srem('close:shop', $param['user']['uid']);
                event(new SystemEvent($param['user']['uid'],trans('notice.title.success.verify'),str_replace('{start_date}',date('Y年m月d日 H:i:s',time()),trans('notice.content.success.verify')),0,-1,'系统管理员'));
                ShopDisable::shopVerifyPass($param['user']['uid']);
            }elseif($param['status'] == 'reject'){
                event(new SystemEvent($param['user']['uid'],trans('notice.title.fail.verify'),str_replace(['{start_date}','{reject_reason}'],[date('Y年m月d日 H:i:s',time()),$param['reject_reason']],trans('notice.content.fail.verify')),0,-1,'系统管理员'));
            }
        }
        return [
            'error_code'    => '0',
            'error_message' => 'success',
        ];
    }

    /**
     * @return array
     * 提现账户设置回调
     */
    public function withdrawAccountCallback(){
        $param = request()->input();
        if($param){
            Shop::where('hashid',$param['uid'])
                ->update(['withdraw_account'=>$param['status'],'account_id'=>$param['id']]);
        }
        return $this->output([
            'success'   => 1
        ]);
    }

    /**
     * 提现回调
     */
    public function withdrawNotify(){
        if(request('status') == 'confirmed'){
            event(new SystemEvent(request('uid'),trans('notice.title.success.withdraw'),str_replace('{start_date}',date('Y年m月d日 H:i:s',request('create_time')),trans('notice.content.success.withdraw')),0,-1,'系统管理员'));
        }elseif(request('status') == 'refused'){
            event(new SystemEvent(request('uid'),trans('notice.title.fail.withdraw'),str_replace('{start_date}',date('Y年m月d日 H:i:s',request('create_time')),trans('notice.content.fail.withdraw')),0,-1,'系统管理员'));
        }
        return $this->output([
            'success'   => 1
        ]);
    }

    /**
     * 提现费率
     * 基础版 0.3%
     * 其它 0.15%
     */
    public function withdrawFeeRate() {
        $param = request()->input();
        $shop = Shop::select('hashid', 'version')
                ->where('hashid', $param['uid'])
                ->firstOrFail();
        switch ($shop->version) {
            case 'basic':
                $rate = 0.03;
                break;
            default:
                $rate = 0.015;
                break;
        }
        return [
                'response' => ['fee_rate'=> $rate]
            ];
    }

    //获取短书店铺信息
    public function getUserInfo(){
        $param = request()->input();
        if($param){
            if(strlen($param['id']) > 18){
                $data = $this->getMemberInfo($param['id']);
                return $this->output($data);
            }else {
                $shop = Shop::where('hashid', $param['id'])->first();
                $shop && $userShop = UserShop::where(['shop_id' => $shop->hashid, 'admin' => 1])->first();
                if ($shop && $userShop) {
                    $user = $userShop->user;
                    $data = [
                        'openid' => $shop->hashid,
                        'title' => $shop->title,
                        'brief' => $shop->brief,
                        'account_id' => trim($shop->account_id),
                        'username' => $user->username ?: '',
                        'nickname' => $user->username ?: '',
                        'email' => $user->email ?: '',
                        'mobile' => $user->mobile ?: '',
                        'avatar' => $user->avatar ?: '',
                        'source' => 'duanshu',
                    ];
                    return $this->output($data);
                }
            }
        }
        return [];
    }

    //获取短书会员信息
    private function getMemberInfo($member_id){

        if($member_id){
            $member = Member::where('uid',$member_id)->first();
            if($member){
                $data = [
                    'openid'=>$member->uid,
                    'title'=>$member->nick_name,
                    'brief'=>$member->true_name,
                    'account_id'    => '',
                    'username' => $member->uid?:'',
                    'nickname' => $member->nick_name?:'',
                    'email'    => $member->email?:'',
                    'mobile'   => $member->mobile?:'',
                    'avatar'   => $member->avatar? : '',
                    'source'   => 'duanshu',
                ];
                return $data;
            }
        }
        return [];
    }

    /**
     * 存入uv值
     */
    public function setUserViews(){
        $this->validateWithAttribute(
            ['type'=> 'required | string |in:home,advanced,partner'],
            ['type'=>'uv类型']
        );
        $catch = Cache::get('uv:'.$this->user['id']);
        if(!$catch){//不在30分钟内，插入信息
            $result = WebsiteSituation::where('type',request('type'))->first();
            $data = ['uv' =>($result['uv'] ? $result['uv']+1 : 1)];
            if($result){
                WebsiteSituation::where('type',request('type'))->update($data);
            }else{
                $tj = ['type' => request('type')];
                $param = array_merge($tj,$data);
                WebsiteSituation::insert($param);
            }
            Cache::put('uv:'.$this->user['id'],$data['uv'],30);
        }
        return $this->output(['success' => 1]);
    }

    /**
     * 存入pv值
     * @return mixed
     */
    public function setPersonViews(){
        $this->validateWithAttribute(
            ['type'=> 'required | string |in:home,advanced,partner'],
            ['type'=>'pv类型']
        );
        $result = WebsiteSituation::where('type',request('type'))->first();
        $data = ['pv' =>($result['pv']? $result['pv']+1 : 1)];
        if($result){
            WebsiteSituation::where('type',request('type'))->update($data);
        }else{
            $tj = ['type' => request('type')];
            $param = array_merge($tj,$data);
            WebsiteSituation::insert($param);
        }
        return $this->output(['success' => 1]);
    }

    /**
     * 存入点击事件数量
     * @return mixed
     */
    public function setClickQuantity(){
        $this->validateWithAttribute(
            ['type'=> 'required | string |in:register,register-code,register-now,register-fail,login,increase,apply,partner-success,partner-fail'],
            ['type'=>'点击类型']
        );
        $result = WebsiteSituation::where('type',request('type'))->first();
        $data = ['quantity' =>($result['quantity']? $result['quantity']+1 : 1)];
        if($result){
            WebsiteSituation::where('type',request('type'))->update($data);
        }else{
            $tj = ['type' => request('type')];
            $param = array_merge($tj,$data);
            WebsiteSituation::insert($param);
        }
        return $this->output(['success' => 1]);
    }


    /**
     * 设置公告展示状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function announceStatus(){

        $this->validateWithAttribute([
            'type'=>'alpha_dash|in:announce,promotion,partner,message'
        ],[
            'type'  => '弹窗公告类型'
        ]);
        $type = request('type') ? : 'announce';
        $hash = 'announce:'.$this->shop['id'];
        Redis::hset($hash,$type,1);
//        !Redis::ttl($hash) && Redis::expire($hash,3600 * 24 * 30);  //设置一个月的过期时间
        return $this->output(['success' => 1]);
    }

    /**
     * 记录高级版升级、全平台申请按钮点击数据
     */
    public function setButtonClicks(){

        $this->validateWithAttribute([
            'type'  => 'required|alpha_dash|in:advanced,fullplat',
        ],[
            'type'  => '按钮类型'
        ]);
        $param = [
            'shop_id'   => $this->shop['id'],
            'user_id'   => $this->user['id'],
            'click_time'    => time(),
            'type'          => request('type')
        ];
        $model = new UserButtonClicks();
        $model->setRawAttributes($param);
        $model->save();
        return $this->output(['success'=>1]);
    }

    public function postage()
    {
        return $this->output(Postage::pluck('content','version'));
    }


    /**
     * 获取首页弹窗展示状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHomeWindowStatus(){
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $status = Cache::get('home:window:'.md5($user_agent));
        return $this->output([
            'is_display'    => intval($status) ? 0 : 1,
        ]);
    }

    /**
     * 修改首页弹窗展示状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeHomeWindowStatus(){
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        Cache::put('home:window:'.md5($user_agent),1,HOME_WINDOW_EXPIRE);
        return $this->output(['success'=>1]);
    }


    /**
     * 会员提现回调
     */
    public function memberWithdrawCallback(){

        //do something

        $return = [
            'error_code'    => '0',
            'error_message' => 'success',
        ];
        return $this->output($return);
    }

    /**
     * 小程序极速登录
     */
    public function appletFastLogin(){

        $this->validateWithAttribute([
            'state' => 'required|numeric|in:0,1',
        ],[
            'state' => '极速登录状态'
        ]);

        $fast_login = request('state') ? 1 : 0;

        $shop = Shop::where('hashid',$this->shop['id'])->firstOrFail();
        $shop->fast_login = $fast_login;
        $shop->save();
        return $this->output(['success'=>1]);

    }

    /**
     * 小程序开启极速登录、ios支付等
     */
    public function shopSetting(){
        $keys = ['fast_login', 'applet_ios_pay', 'enable_customer_service'];
        $advanced_keys = ['enable_customer_service'];
        $advanced_keys_map = [
            'enable_customer_service' => '小程序客服'
        ];
        $params = request()->all();
        $shop = Shop::where('hashid', $this->shop['id'])->firstOrFail();
        foreach ($params as $key => $val) {
            if (in_array($key, $keys)) {
                $shop->$key = $val;
            }
            if (in_array($key, $advanced_keys)) {
                if ($this->shop['version'] == VERSION_ADVANCED || $this->shop['version'] == VERSION_STANDARD) {
                    $shop->$key = $val;
                } else {
                    $this->error('low_version', ['attributes' => $advanced_keys_map[$key]]);
                }
            }
        }
        $shop->save();
        return $this->output(['success' => 1]);
    }


    /**
     * 小程序退款上传证书接口
     */
    public function uploadCertificate(){
        $this->validateWithAttribute([
            'file'  => 'required|file',
            'type'  => 'required|alpha_dash|in:cert,key',
        ],[
            'file'  => '证书文件',
            'type'  => '证书类型',
        ]);
        $file = request('file');
        if($file->getClientOriginalExtension() != 'pem'){
            $this->error('file-type-error');
        }
        $certificate_path = base_path('certificate/').$this->shop['id'].'/';
        //判断路径是否存在
        if(!is_dir($certificate_path)){
            mkdir($certificate_path,0777,true);
        }
        //判断文件夹是否可写入
        if(!is_writable($certificate_path)){
            chmod($certificate_path,0777);
        }
        switch (request('type')){
            case 'cert':
                $file_name = 'apiclient_cert.pem';
                break;
            case 'key':
                $file_name = 'apiclient_key.pem';
                break;
            default:
                $file_name = 'apiclient_cert.pem';
                break;
        }
        //文件存储到证书文件夹
        $file->move($certificate_path,$file_name);
//        //上传到cos
//        $certificate_file = $certificate_path.$file_name;
//        $cos_path = config('qcloud.folder').'/certificate/'.$this->shop['id'].'/'.$file_name;
//        Cosapi::setRegion(config('qcloud.region'));
//        $data = Cosapi::upload(config('qcloud.cos.bucket'),$certificate_file,$cos_path);
//        $data['code'] && $this->errorWithText($data['code'],$data['message']);
//        //上传完成直接开通
//        Shop::where(['hashid'=>$this->shop['id']])->update(['is_applet_refund'=>1]);

        //判断证书文件是否存在，如果存在开通退款
        $file_array = scandir($certificate_path);
        $file = ['apiclient_key.pem','apiclient_cert.pem'];
        if(!array_diff($file,$file_array)){
            Shop::where(['hashid'=>$this->shop['id']])->update(['is_applet_refund'=>1]);
            Cache::forget('share:'.$this->shop['id']);
            Cache::forever('change:'.$this->user['id'],1);

        }
//        @unlink($certificate_path.$file_name);
//        if(count(scandir($certificate_path))==2) {  //删除空目录，=2是因为./..
//            rmdir($certificate_path);
//        }
        return $this->output(['success'=>1]);

    }

    /**
     * 关闭小程序退款功能
     */
    public function closeAppletRefund()
    {
        $is_applet_refund = Shop::where(['hashid' => $this->shop['id']])->value('is_applet_refund');
        if (!$is_applet_refund) {
            $this->error('not-open-applet-refund');
        }
        $is_fight_activity = FightGroupActivity::where('start_time', '<', hg_format_date(strtotime('+8 hour')))->where('end_time', '>', hg_format_date(strtotime('+8 hour')))->first();
        if ($is_fight_activity) {
            $this->error('marketing-activity-in-service');
        }
//        $cos_path = config('qcloud.folder').'/certificate/'.$this->shop['id'].'/cert.pem';
//        Cosapi::setRegion(config('qcloud.region'));
//        $data = Cosapi::delFile(config('qcloud.cos.bucket'),$cos_path);
//        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        //删除证书文件
        $certificate_path = base_path('certificate/') . $this->shop['id'] . '/';
        @unlink($certificate_path . 'apiclient_cert.pem');
        @unlink($certificate_path . 'apiclient_key.pem');
        if (count(scandir($certificate_path)) == 2) {  //删除空目录，=2是因为./..
            rmdir($certificate_path);
        }

        Shop::where(['hashid' => $this->shop['id']])->update(['is_applet_refund' => 0]);
        Cache::forget('share:' . $this->shop['id']);
        Cache::forever('change:' . $this->user['id'], 1);
    }
    /**
     * @return \Illuminate\Http\JsonResponse
     * 设置店铺信息
     */
    public function setShopInfo(){
        $this->validateWithAttribute(['sign'=>'required'],['sign'=>'信息标识']);
        $sign = request('sign');
        $shopInfo = ShopInfo::where(['shop_id'=>$this->shop['id']])->first();
        !$shopInfo && $shopInfo = new ShopInfo();
        switch ($sign){
            case 'telephone':
                $shopInfo->shop_id = $this->shop['id'];
                $shopInfo->telephone = request('telephone');
                $shopInfo->save();
                break;
            case 'address':
                $shopInfo->shop_id = $this->shop['id'];
                $shopInfo->address = request('address');
                $shopInfo->save();
                break;
            case 'public':
                $shopInfo->shop_id = $this->shop['id'];
                $shopInfo->public_name = request('public_name');
                $shopInfo->public_indexpic = hg_explore_image_link(request('public_indexpic'));
                $shopInfo->save();
                break;
            case 'indexpic':
                $shopInfo->shop_id = $this->shop['id'];
                $indexpic = request('indexpic');$images = [];
                if($indexpic && is_array($indexpic)){
                    foreach ($indexpic as $item){
                        $images[] = hg_explore_image_link($item);
                    }
                }
                $shopInfo->indexpic = serialize($images);
                $shopInfo->save();
                break;
            case 'title':
                Shop::where(['hashid'=>$this->shop['id']])->update(['title'=>request('title')]);
                break;
            case 'brief':
                Shop::where(['hashid'=>$this->shop['id']])->update(['brief'=>request('brief')]);
                break;
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 获取证书内容
     * @param $shop_id
     * @param $file_name
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCertificate($shop_id,$file_name)
    {

        $certificate_path = $certificate_path = base_path('certificate/') . $shop_id . '/' . $file_name;
        if (file_exists($certificate_path)) {
            return response()->file($certificate_path);
        }
        $this->error('file-not-exists');
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 店铺信息详情
     */
    public function shopInfoDetail(){
        $shop = Shop::where('hashid',$this->shop['id'])->first();
        $info= []; $brief = '';
        if($shop->brief !='店铺的描述'){
            $brief = $shop->brief;
        }
        if($shop){
            $info = [
                'shop_id' => $this->shop['id'],
                'title' => $shop->title?:'',
                'brief' => $brief,
                'telephone' => $shop->info?($shop->info->telephone?:''):'',
                'address' => $shop->info?($shop->info->address?:''):'',
                'public_name' => $shop->info?($shop->info->public_name?:''):'',
                'public_indexpic' => $shop->info?($shop->info->public_indexpic?hg_unserialize_image_link($shop->info->public_indexpic):[]):[],
            ];
            $images = $shop->info?($shop->info->indexpic?unserialize($shop->info->indexpic):[]):[];
            if($images){
                foreach ($images as $image){
                    $info['indexpic'][] = hg_unserialize_image_link($image);
                }
            }else{
                $info['indexpic'] = [];
            }
        }
        return $this->output($info);
    }
}