<?php

namespace App\Http\Controllers\Manage\Shop;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Customer;
use App\Models\Manage\Order;
use App\Models\Manage\Postage;
use App\Models\Manage\Shop;
use App\Models\Manage\ShopMultiple;
use App\Models\Manage\Users;
use App\Models\Manage\UserShop;
use App\Models\Manage\VersionOrder;
use App\Models\Manage\VersionExpire;
use App\Models\ShopClose;
use App\Models\ShopDisable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ShopController extends BaseController
{
    /**
     * 列表
     *
     * @return mixed
     */
    public function lists()
    {
        $this->validateWith([
            'count' => 'numeric',
            'title' => 'string',
        ]);
        $count = request('count') ?: 10;
        $shop = [];
        if(request('mobile')){
            $shop = Users::where('mobile','like','%'.request('mobile').'%')
                ->leftJoin('user_shop as us','us.user_id','=','users.id')
                ->pluck('shop_id')->toArray();
        }
        $shop_id = $shop ? : (request('shop_id') ? [trim(request('shop_id'))] : []);
        $result = Shop::select('hashid as shop_id', 'title', 'brief', 'create_time');
        request('title') && $result->where('title','like', '%'.request('title').'%');
        $shop_id && $result->whereIn('hashid', $shop_id);
        $result = $result->orderBy('create_time','desc')->paginate($count);
        foreach ($result as $item) {
            $item->create_time = $item->create_time ? hg_format_date($item->create_time) : '';
        }
        return $this->output($this->listToPage($result));
    }

    /**
     * 商铺version修改
     * @return \Illuminate\Http\JsonResponse
     */
    public function update()
    {
        if( !Auth::user()->hasRole('master')){
            return response([
                'error'   => 'no-enouph-permission',
                'message' => trans('validation.no-enouph-permission'),
            ]);
        }
        $this->validateWith([
            'shop_id' => 'required|alpha_num',
            'version' => 'required|string|in:partner,basic,standard,advanced,unactive-partner'
        ]);
        $shop_id = request('shop_id');
        $version = request('version');
        $time = request('time');
        $version_expire = VersionExpire::where(['hashid' => $shop_id,'version'=>$version, 'is_expire' => 0])->orderByDesc('expire')->first();
        switch (request('version')){
            case VERSION_BASIC:
                $this->setShopBasicVersion($shop_id);
                break;
            case VERSION_STANDARD:
                $this->setShopStanardVersion($shop_id, $version_expire);
                break;
            case VERSION_ADVANCED:
                $this->setShopVersion($shop_id, VERSION_ADVANCED, $time, $version_expire);
                ShopDisable::shopUpgradeAdvanced($shop_id);
                break;
            case 'partner':
                $this->setShopVerion($shop_id, VERSION_PARTNER, $time, $version_expire);
                ShopDisable::setShopExpireEnable($shop_id);
                break;
            default: break;
        }
        $shop = Shop::where('hashid', $shop_id)->first();
        $shop->version = $version ?: $shop->version;
        $shop->save();

        if($version == VERSION_STANDARD || $version==VERSION_ADVANCED){
            $this->postVersionUpdate($shop);
        }

        $userShop = UserShop::where('shop_id', request('shop_id'))->get();
        if ($userShop) {
            foreach ($userShop as $v) {
                Cache::forever('change:'.$v->user_id,1);
            }
        }
//        Redis::srem('close:shop', request('shop_id'));
        return $this->output(['success'=>1]) ;
    }


    /**
     * 设置基础版本
     * @param $shop_id
     */
    private function setShopBasicVersion($shop_id){
        $shop = Shop::where(['hashid'=>$shop_id])->first();
        if($shop){
            //所有版本的到期时间设置为过期
            VersionExpire::where(['hashid'=>$shop_id])->where('expire','>',time())->update(['expire'=>time()]);
            //到期关店的重新开启
            ShopDisable::setShopExpireEnable($shop_id);
            if($shop->verify_expire > time()){
                //认证成功且未过期
                if($shop->verify_status== 'success'){
                    $version = new VersionExpire();
                    $version->hashid = $shop_id;
                    $version->version = VERSION_BASIC;
                    $version->start = time();
                    $version->expire = $shop->verify_expire;
                    $version->is_expire = 0;
                    $version->method = 1;
                    $version->saveOrFail();
                }else{
                    ShopDisable::createOrDoNotExistShopDisable($shop_id, SHOP_DISABLE_BASIC_TEST_EXPIRE);
                }
            }else{
                //认证状态设置为无效
                $shop->verify_status = 'invalid';
                $shop->saveOrFail();
                //7天试用
                $version = new VersionExpire();
                $version->hashid = $shop_id;
                $version->version = VERSION_BASIC;
                $version->start = time();
                $version->expire = strtotime('+7day',time());
                $version->is_expire = 0;
                $version->method = 2;
                $version->saveOrFail();
            }
        }
    }

    /**
     * @param $shop_id
     * @param $time
     * @param $version_expire
     */
    private function setShopStanardVersion($shop_id, $version_expire){
        $shop = Shop::where(['hashid' => $shop_id])->first();
        if ($shop) {
            VersionExpire::where(['hashid' => $shop_id, 'version' => 'advanced'])
                ->where('expire', '>', time())
                ->update(['expire' => time(), 'is_expire' => 1]);
            ShopDisable::shopUpgradeAdvanced($shop_id);
            // 返回标准版 如果已过期赠送7天试用
            if (!$version_expire || $version_expire->expire < time()) {
                $version = new VersionExpire();
                $version->hashid = $shop_id;
                $version->version = VERSION_STANDARD;
                $version->start = time();
                $version->expire = strtotime('+7day', time());
                $version->is_expire = 0;
                $version->method = 2;
                $version->saveOrFail();
            }
        }
    }

    /**
     * @param $shop_id
     * @param $version
     * @param $time
     * @param $version_expire
     */
    private function setShopVersion($shop_id, $version, $time, $version_expire){
        $shop = Shop::where(['hashid' => $shop_id])->first();
        if($shop){
            if ($version_expire) {
                $start = $version_expire->expire > time() ? $version_expire->expire : time();
            } else {
                $start = time();
            }
            $ve = new VersionExpire();
            $ve->hashid = $shop_id;
            $ve->version = $version;
            $ve->start = $start;
            $ve->expire = strtotime($time,$start);
            $ve->method = 2;
            $ve->save();
            if($time == '+1 year' || $time == '+2 year'){
                Customer::where('shop_id', $shop_id)->update(['cooperation' => 1]);
            }
        }
    }


    /**
     * @param $shop
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function postVersionUpdate($shop)
    {
        $id = $shop->id;
        $shop_id = $shop->hashid;
        try {
            $timestamp = time();
            $param = ['shop' => $id];
            $app_id = config('define.inner_config.sign.key');
            $app_secret = config('define.inner_config.sign.secret');
            $client = hg_verify_signature($param, $timestamp, $app_id, $app_secret, $shop_id);
            $url = config('define.python_duanshu.api.internal_version_update');
            $res = $client->request('POST', $url);
            $response = $res->getBody()->getContents();
            event(new CurlLogsEvent($response, $client, $url));
        } catch (\Exception $exception) {
            event(new ErrorHandle($exception, 'notify python version update'));
        }
    }


    /**
     * 处理高级版
     * @param $shop
     */
//    private function setVersionOrder($shop){
//
//        $order_time = VersionOrder::where(['shop_id'=>$shop->hashid,'order_no'=>-1])->orderBy('success_time')->value('success_time');
//        if($shop->version == 'advanced' && (!$order_time ||($order_time && ($order_time + 3600 * 24 * 365 < time())))){
//            $param = [
//                'shop_id'      => $shop->hashid,
//                'product_id'   => -1,
//                'product_name' => '',
//                'brief'        => '',
//                'category'     => '',
//                'thumb'        => '',
//                'type'         => 'permission',
//                'sku'          => serialize([
//                    'properties' => [
//                        0 => [
//                                'k' => '有效期',
//                                'v' => '一年'
//                            ]
//                    ]
//                ]),
//                'unit_price'   => 0,
//                'quantity'     => 0,
//                'total'        => 0,
//                'meta'         => '',
//                'order_no'     => -1,
//                'success_time' => time(),
//                'create_time'  => time(),
//            ];
//            $versionOrder = new VersionOrder();
//            $versionOrder->setRawAttributes($param);
//            $versionOrder->save();
//
//            //高级版过期表处理
//            $expire = VersionExpire::where('hashid',$shop->hashid)->orderByDesc('expire')->first();
//            if($expire){
//                $start = $expire->expire > time() ? $expire->expire : time();
//            }else{
//                $start = time();
//            }
//            $str = '+12month';
//            $end = strtotime($str,$start);
//            VersionExpire::insert([
//                'hashid' => $shop->hashid,
//                'version' => 'advanced',
//                'start' => $start,
//                'expire'   => $end,
//                'method'    => 2,
//            ]);
//        }
//
//    }

    /**
     * 增长统计
     *
     * @return mixed
     */
    public function shopCount()
    {
        $todayIncrease = $this->todayIncrease();
        $allIncrease = $this->allIncrease();
        return $this->output(['todayIncrease' => $todayIncrease, 'allIncrease' => $allIncrease]);
    }

    private function todayIncrease()
    {
        $counts = Shop::whereBetween('create_time', [strtotime(date('Y-m-d 00:00:00', time())), time()])->count('id');
        return $counts ?: 0;
    }

    private function allIncrease()
    {
        $counts = Shop::count('id');
        return $counts ?: 0;
    }

    /**
     * 获取单个用户下的商铺信息
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLists()
    {
        $this->validateWith([
            'user_id' => 'required|numeric', 'count' => 'numeric'
        ]);
        $shop_id = $this->getShopIdByUid(request('user_id'));
        $shop = $this->getLists($shop_id);
        $this->handleLists($shop);
        return $this->output($shop);
    }

    /**
     * 通过user_id 在user_shop表中获取shop_id
     *
     * @param string  $uid user_id
     * @param integer $count
     *
     * @return array
     */
    private function getShopIdByUid($uid = '')
    {
        if (!$uid) {
            return [];
        }
        return UserShop::where('user_id', $uid)->pluck('shop_id');
    }

    /**
     * 通过shop_id获取商店的详情
     *
     * @param $sid shop_id
     *
     * @return array
     */
    private function getLists($sid)
    {
        if (!$sid) {
            return [];
        }
        $count = request('count') ?: 15;
        $page = Shop::whereIn('hashid', $sid)->orderBy('create_time', 'desc')->paginate($count);
        return $this->listToPage($page);
    }
    
    private function handleLists($shop)
    {
        if ($shop['data']) {
            foreach ($shop['data'] as $item) {
                $item->create_time && $item->create_time = hg_format_date($item->create_time);
            }
        }
    }

    public function chgStatus()
    {
        $this->validateWith([
            'shop_id'     => 'required|alpha_num',
            'status' => 'required|numeric'
        ]);
        $ret = Shop::where('hashid', request('shop_id'))->update(['status' => request('status')]);
        return $this->output(['success'=>1]);
    }


    /**
     * 根据shop_id获取商店详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopDetail()
    {
        $this->validateWith([
            'shop_id'   => 'required|alpha_dash'
        ]);
        $detail = Shop::where('hashid',request('shop_id'))->first();
        if ($detail) {
            $detail->create_time = $detail->create_time ? hg_format_date($detail->create_time) : '';
            $detail->money = round(Order::where(['shop_id' => request('shop_id'),'pay_status'=>1])->sum('price'),2);

//            if (Redis::sismember('close:shop',$detail->hashid)) {
//                $detail->status = false;
//            } else {
//                $detail->status = true;
//            }
            if(ShopDisable::isShopDisable($detail->hashid)){
                $detail->status = false;
            }else{
                $detail->status = true;
            }
            if ($detail->is_black == 1) {
                $detail->is_black  = true;
            } elseif ($detail->is_black == 0) {
                $detail->is_black  = false;
            }
            if($detail->shopMultiple && is_numeric($detail->shopMultiple->multiple))
            {
                $multiple = $detail->shopMultiple->multiple;
                $range = $detail->shopMultiple?unserialize($detail->shopMultiple->range):[];
                $detail->multiple = [
                    'view' => ['checked' => in_array('1',$range), 'multiple' => $multiple, 'base' => 0],
                    'subscribe' => ['checked' => in_array('2',$range), 'multiple' => $multiple, 'base' => 0],
                    'online' => ['checked' =>  in_array('3',$range), 'multiple' => $multiple, 'base' => 0],
                ];
            }else{
                 $multiple = $detail->shopMultiple?unserialize($detail->shopMultiple->multiple):[];
                $range = $detail->shopMultiple?unserialize($detail->shopMultiple->range):[];
                $base = $detail->shopMultiple?unserialize($detail->shopMultiple->base):[];
                $type = ['view','subscribe','online'];
                foreach ($type as $v){
                    $m[$v] = [
                        'checked' => isset($range[$v]) && $range[$v] ? true : false,
                        'multiple'    =>    isset($multiple[$v]) ? $multiple[$v] : 1,
                        'base'    =>    isset($base[$v]) ? $base[$v] : 0,
                    ];
                }
                 $detail->multiple = $m;
            }
            $detail->makeHidden(['shopMultiple']);
        }
        return $this->output($detail ? : []);
    }



    /************2017-6-20***************/




    /**
     * 根据shop_id查询商店所有用户
     * @return mixed
     */
    public function getshopInfoBySid()
    {
        $this->validateWith([
            'shop_id' => 'required|alpha_dash',
            'count'   => 'numeric'
        ]);//18位字符
        $count = request('count') ? : 10 ;
        $users = UserShop::where('shop_id',request('shop_id'))
            ->join('users','users.id','=','user_shop.user_id')
            ->select('user_shop.user_id','user_shop.shop_id','user_shop.effect','user_shop.admin','users.name','users.mobile','users.email')
            ->paginate($count);
        return $this->output($this->listToPage($users));
    }



    /********************2017-7-10*********************/


    /**
     * 商铺黑名单管理
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopBlack()
    {
        $this->validateWith([
           'shop_id'   => 'required|alpha_dash',
            'black'    => 'required|numeric|in:0,1'
        ]);
        Shop::where('hashid',request('shop_id'))->update(['is_black'=>request('black')]);
        if(intval(request('black'))==1){
            Redis::sadd('black:shop',request('shop_id'));
        }else{
            Redis::srem('black:shop',request('shop_id'));
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 提现
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCash()
    {
        $this->validateWith([
            'shop_id'   => 'required|alpha_dash'
        ]);
        $data = ['uid'=>request('shop_id')];
        $client = hg_verify_signature($data,'','','',request('shop_id'));
        $url = config('define.order_center.api.withdraw_money');
        try {
            $res = $client->request('GET',$url,['query'=>$data]);
        } catch (\Exception $e) {
            $res = $e->getMessage();
            event(new CurlLogsEvent(json_encode($res),$client,$url));
            $this->error('error-withdraw-money');
        }
        $data = json_decode($res->getBody()->getContents(),1);
        event(new CurlLogsEvent(json_encode($data),$client,$url));
        if ($res->getStatusCode() !== 200) {
            $this->error('error-withdraw-money');
        }
        if ($res && $data['error_code']) {
            $this->errorWithText('error-withdraw-money-'.$data['error_code'], $data['error_message']);
        }
        if ($data['result']) {
            $data['result']['available'] = isset($data['result']['available']) ? round($data['result']['available'] / 100,2) : 0.00;
            $data['result']['pending'] = isset($data['result']['pending']) ? round($data['result']['pending'] / 100,2) : 0.00;
            $data['result']['confirmed'] = isset($data['result']['confirmed']) ? round($data['result']['confirmed'] / 100,2) : 0.00;
            $data['result']['pending_receipt'] = isset($data['result']['pending_receipt']) ? round($data['result']['pending_receipt']  / 100,2) : 0.00;
            $data['result']['confirmed_receipt'] = isset($data['result']['confirmed_receipt']) ? round($data['result']['confirmed_receipt'] / 100,2) : 0.00;
            $data['result']['income'] = isset($data['result']['income']) ? round($data['result']['income'] / 100,2) : 0.00;
        }
        return $this->output($data['result']);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 店铺倍数
     */
    public function multiple(){
        $this->validateWithAttribute(['shop_id'=>'required','multiple'=>'required'],['shop_id'=>'店铺id','multiple'=>'倍数','range'=>'作用范围']);
        $multiple = ShopMultiple::where('shop_id',request('shop_id'))->firstOrCreate(['shop_id'=>request('shop_id')]);
        $m = request('multiple');
        if($m && is_array($m)){
            foreach ($m as $key=>$value){
                $base[$key] = $value['base'];
                $mul[$key] = $value['multiple'];
                $range[$key] = $value['checked'];
            }
        }

        $multiple->base = serialize($base);
        $multiple->multiple = serialize($mul);
        $multiple->range = serialize($range);
        $multiple->save();
        Redis::del('multiple:'.request('shop_id'));
        return $this->output($multiple);
    }
    
    public function postage()
    {
        $data = Postage::where('version',request('version'))->first();
        $data = [
            'version' => request('version'),
            'content'   => $data ? $data->content : '',
        ];
        return $this->output($data);
    }

    public function savePostage()
    {
        $data = Postage::where('version',request('version'))->first();
        if(!$data){
            Postage::insert([
                'version' => request('version'),
                'content'   => request('content'),
            ]);
        }else{
            $data->content = request('content');
            $data->save();
        }
        return $this->output([
            'version'  => request('version'),
            'content'   => request('content'),
        ]);
    }

    /**
     * 7天高级版体验权限
     */
    public function sevenPerm()
    {
        $this->error('function-maintain');
//        $this->validateWith([
//            'shop_id' => 'required|alpha_num',
//            'time' => 'required',
//        ]);
//        $time = request('time');
//        if($time != '+3 days' && $time != '+7 days'){
//            return $this->error('error-time');
//        }
//        $shop_id = request('shop_id');
//        $version = request('version');
//        if($version != VERSION_ADVANCED){
//            return $this->error('version-only-advanced');
//        }
//        $version_expire = VersionExpire::where(['hashid' => $shop_id,'version'=>$version, 'is_expire' => 0])->orderByDesc('expire')->first();
//        if ($version_expire) {
//            $now = time();
//            $expireDays = ($version_expire->expire - $now) / (60*60*24);
//            if($expireDays > 30) {
//                return $this->error('adv-ex-expire-too-long');
//            }
//        }
//        if($version_expire){
//            $start = $version_expire->expire > time() ? $version_expire->expire : time();
//        }else{
//            $start = time();
//        }
//        $ve = new VersionExpire();
//        $ve->hashid = $shop_id;
//        $ve->version = VERSION_ADVANCED;
//        $ve->start = $start;
//        $ve->expire = strtotime($time,$start);
//        $ve->method = 2;
//        $ve->save();
//
//        $shop = Shop::where('hashid', $shop_id)->first();
//        $shop->version = VERSION_ADVANCED;
//        $shop->verify_expire = $ve->expire;
//        $shop->save();
//        $userShop = UserShop::where('shop_id', $shop_id)->get();
//        if ($userShop) {
//            foreach ($userShop as $v) {
//                Cache::forever('change:'.$v->user_id,1);
//            }
//        }
//
//        Customer::where('shop_id',$shop_id)->update(['cooperation'=>0]);
//        ShopDisable::shopUpgradeAdvanced($shop_id);
//        return $this->output(['success'=>1]) ;
    }

    /**
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function versionProbation(){
        $this->validateWith([
            'shop_id' => 'required|alpha_num',
            'version' => 'required|string|in:standard,advanced'
        ]);
        $time = request('time');
        $version = request('version');
        if($version == VERSION_ADVANCED && $time != '+3 days' && $time != '+7 days'){
            return $this->error('error-time');
        }
        $shop_id = request('shop_id');
        $version_expire = VersionExpire::where(['hashid' => $shop_id,'version'=>$version, 'is_expire' => 0])->orderByDesc('expire')->first();

        if($version == VERSION_ADVANCED){
            if ($version_expire) {
                $now = time();
                $expireDays = ($version_expire->expire - $now) / (60 * 60 * 24);
                if($expireDays > 30) {
                    return $this->error('adv-ex-expire-too-long');
                }
            }
            if($version_expire){
                $start = $version_expire->expire > time() ? $version_expire->expire : time();
            }else{
                $start = time();
            }
            $ve = new VersionExpire();
            $ve->hashid = $shop_id;
            $ve->version = VERSION_ADVANCED;
            $ve->start = $start;
            $ve->expire = strtotime($time,$start);
            $ve->method = 2;
            $ve->save();

            $shop = Shop::where('hashid', $shop_id)->first();
            $shop->version = VERSION_ADVANCED;
            $shop->verify_expire = $ve->expire;
            $shop->save();
            $userShop = UserShop::where('shop_id', $shop_id)->get();
            if ($userShop) {
                foreach ($userShop as $v) {
                    Cache::forever('change:'.$v->user_id,1);
                }
            }

            Customer::where('shop_id',$shop_id)->update(['cooperation'=>0]);
            ShopDisable::shopUpgradeAdvanced($shop_id);

            $this->postVersionUpdate($shop);


        } else if($version == VERSION_STANDARD){
            $this->setShopStanardVersion($shop_id, $version_expire);
            $shop = Shop::where('hashid', $shop_id)->first();
            $shop->version = $version ?: $shop->version;
            $shop->save();

            $this->postVersionUpdate($shop);

            $userShop = UserShop::where('shop_id', request('shop_id'))->get();
            if ($userShop) {
                foreach ($userShop as $v) {
                    Cache::forever('change:'.$v->user_id,1);
                }
            }
        }
        return $this->output(['success'=>1]) ;

    }

}




