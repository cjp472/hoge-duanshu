<?php
/**
 * 后台用户
 * Gh 2017-4-24
 */

namespace App\Http\Controllers\Manage\User;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Shop;
use App\Models\Manage\TryUser;
use App\Models\Manage\UserButtonClicks;
use App\Models\Manage\Users;
use App\Models\Manage\VersionExpire;
use App\Models\Manage\VersionOrder;
use App\Models\Member;
use App\Models\OpenPlatformApplet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 用户列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function lists()
    {
        $this->validateWith([
            'page'       => 'numeric',
            'count'      => 'numeric|max:10000',
            'name'       => 'alpha_dash|max:64',
            'email'      => 'max:64',
            'mobile'     => 'max:64',
            'order'      => 'alpha_dash|in:desc,asc'
        ]);
        $data = $this->getUserList();
        return $this->output($data);
    }

    /**
     * 用户详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail($id)
    {
        $data = $this->getUserDetail($id);
        if ($data) {
            $data->active = $data->active ? true : false;
        }
        return $this->output($data);
    }

    private function getUserList()
    {
        $count = request('count') ?: 15;
        $users = Users::select('users.id', 'name', 'email', 'mobile', 'users.created_at','login_time','channel','agent', 'search_word', 'search_engine', 'device');
        if (request('name')) {
            $users->where('name', 'like', '%' . request('name') . '%');
        }
        if (request('email')) {
            $users->where('email', 'like', '%' . request('email') . '%');
        }
        if (request('mobile')) {
            $users->where('mobile', 'like', '%' . request('mobile') . '%');
        }
        request('order') && $users->orderBy('login_time',request('order'));
        request('new') && $users->whereBetween('users.created_at',[date('Y-m-d 00:00:00'),hg_format_date()]);
        $users->leftJoin('user_shop','users.id','user_shop.user_id');
        $users->leftJoin('shop','shop.hashid','user_shop.shop_id');
        $users->leftJoin('regist_track', 'users.id', '=', 'regist_track.user_id');
        $users->addSelect('shop.agent','shop.channel','shop.hashid');
        $users->where('user_shop.admin', '=', 1);
        $page = $users->orderBy('created_at', 'desc')->paginate($count);
        if ($page->items()) {
            foreach ($page->items() as $item) {
                $item->login_time = $item->login_time ? hg_format_date($item->login_time) : '';
                $item->shop_id = $item->hashid;
            }
        }
        return $this->listToPage($page);
    }

    /**
     * 用户修改
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update()
    {
        $this->validateWith([
            'id'      => 'required|numeric',
            'active'  => 'required|numeric|in:0,1'
        ]);
        $user = User::find(request('id'));
        $user->active = request('active');
        $user->save();
        return $this->output(['success'=>1]);

    }

    private function getUserDetail($id = '')
    {
        if (!$id) return [];
        $users = new Users();
        $data = $users
            ->select('users.id', 'name', 'email', 'active', 'users.created_at', 'users.updated_at', 'search_word', 'search_engine')
            ->leftJoin('regist_track', 'users.id', '=', 'regist_track.user_id')
            ->where('users.id', $id)
            ->first();
        return $data;
    }

    /**
     * 用户黑名单管理
     * @return \Illuminate\Http\JsonResponse
     */
    public function userBlack()
    {
        $this->validateWith([
            'user_id'    =>   'required|numeric',
            'black'      =>   'required|numeric|in:0,1'       //1加入黑名单，0移除黑名单
        ]);
        Users::where('id',request('user_id'))->update(['is_black'=>request('black')]);
        return $this->output(['success'=>1]);
    }

    /*
     * 试用用户列表
     */
    public function tryUserLists(){
        $count = request('count') ? : 10;
        $data = TryUser::leftJoin('users','try_user.mobile','=','users.mobile')
            ->whereNUll('users.mobile')
            ->select('try_user.*')
            ->orderBy('try_user.create_time','desc')
            ->paginate($count);
        foreach ($data as $item) {
            $item->create_time = hg_format_date($item->create_time);
        }
        return $this->output($this->listToPage($data));
    }

    /**
     * 短书高级版用户列表
     */
    public function advancedUserList(){

        $this->validateWith([
            'page'       => 'numeric',
            'count'      => 'numeric|max:10000',
        ]);
        $count = request('count') ?: 15;
        $applet = OpenPlatformApplet::pluck('shop_id')->toArray();
        $sql = VersionExpire::orderBy('version_expire.created_at','desc')
            ->leftJoin('user_shop','user_shop.shop_id','version_expire.hashid')
            ->leftJoin('users','user_shop.user_id','users.id')
//            ->where('version_expire.start','<',time())
//            ->orWhere('version_expire.method',1)
            ->where('version_expire.version',VERSION_ADVANCED)
            ->where('user_shop.admin',1);
        if(request('mobile')){
            $sql->where('users.mobile',request('mobile'));
        }
        if(request('method') != null){
            $sql->where('version_expire.method',request('method'));
        }
        if(request('is_expire') != null){
            $sql->where('version_expire.is_expire',request('is_expire'));
        }
        $is_applet = request('is_applet');
        if(isset($is_applet) && $is_applet==1){
            $sql->whereIn('version_expire.hashid',$applet);
        }elseif (isset($is_applet) && intval($is_applet)==0){
            $sql->whereNotIn('version_expire.hashid',$applet);
        }

        $advanced = $sql->select('users.name','users.mobile','users.created_at as create')
            ->addSelect('version_expire.*')
            ->paginate($count,['shop.hashid as shop_id','shop.create_time','version_expire.expire','version_expire.start as start']);

        if(!$advanced->isEmpty()){
            foreach ($advanced->items() as $key=>$item) {
                $item->start_time = $item->start ? date('Y-m-d H:i',$item->start) : '';
                if(!$item->is_expire && ($item->expire-time()) < 5*86400 ){
                    $item->expireing = 1;
                }else{
                    $item->expireing = 0;
                }
                if(!$item->expireing){
                    $item->expire = $this->getExpireTime($item->hashid, VERSION_ADVANCED);
                }
                $item->expire_time = $item->expire ? date('Y-m-d H:i',$item->expire) : '';
                $item->is_applet = in_array($item->hashid,$applet)?1:0;
            }
        }
        return $this->output($this->listToPage($advanced));
    }

    private function getExpireTime($hashid, $version)
    {
        return VersionExpire::where(['hashid' => $hashid, 'version'=>$version, 'is_expire' => 0])->max('expire');
    }

    public function advancedUserExport(){
        $applet = OpenPlatformApplet::pluck('shop_id')->toArray();

        $sql = VersionExpire::orderBy('version_expire.start','desc')
            ->leftJoin('user_shop','user_shop.shop_id','version_expire.hashid')
            ->leftJoin('users','user_shop.user_id','users.id')
            ->leftJoin('shop','user_shop.shop_id','shop.hashid')
            ->where('version_expire.start','<',time())
            ->where('user_shop.admin',1);
        if(request('mobile')){
            $sql->where('users.mobile',request('mobile'));
        }
        if(request('method') != null){
            $sql->where('version_expire.method',request('method'));
        }
        if(request('is_expire') != null){
            $sql->where('version_expire.is_expire',request('is_expire'));
        }
        $is_applet = request('is_applet');
        if(isset($is_applet) && $is_applet==1){
            $sql->whereIn('user_shop.shop_id',$applet);
        }elseif (isset($is_applet) && intval($is_applet)==0){
            $sql->whereNotIn('user_shop.shop_id',$applet);
        }
        $advanced = $sql->select('users.name','users.mobile','shop.hashid as shop_id','shop.verify_status','shop.verify_first_type')->get();

        if(!$advanced->isEmpty()){
            $data[] = ['用户账号','手机','店铺id','认证状态','认证类型','是否设置小程序','会员数'];
            foreach ($advanced as $key=>$item) {
                $item->is_applet = in_array($item->shop_id,$applet)?'是':'否';
                $item->member_num = Member::where(['shop_id'=>$item->shop_id])->count()?:0;
                $item->verify_status = $item->verify_status=='success'?'已认证':($item->verify_status=='processing'?'处理中':($item->verify_status=='reject'?'已驳回':'未认证'));
                $item->verify_first_type = $item->verify_first_type =='personal'?'个人类型':($item->verify_first_type=='enterprise'?'企业类型':($item->verify_first_type=='commonweal'?'公益组织类型':'无'));
                $data[] = $item->toArray();
            }
            Excel::create('高级版用户',function ($excel) use($data) {
                $excel->sheet('高级版用户', function($sheet) use($data) {
                    $sheet->fromArray($data,null,'A1',false,false);
                });
            })->export('xls');
        }
    }

    /**
     * 获取高级版类型，购买/手动设置
     * @param $version
     * @return int
     */
    private function getVersionType($version){
        $order_no = array_column($version,'order_no');
        $diff = array_diff($order_no,['-1']);
        return $diff ? 1 : 0;
    }


    /**
     * 获取过期时间
     * @param $version
     * @return int
     */
    private function getVersionExpire($version){

        $expire = 0;
        if($version){
            $expire_time = 0;$success_time = $return = [];
            foreach ($version as $key=>$item) {
                $sku = $item['sku'] ? unserialize($item['sku']) : [];

                $years = config('define.year_time.' . $sku['properties'][0]['v']);
                if ($years) {
                    $expire_time += $years;
                    $success_time[] = $item['success_time'];
                }else{
                    $expire_time = -1;
                    $success_time[] = $item['success_time'];
                }
            }
            if($success_time && $expire_time) {
                //将有效期时间(月份)加到购买时间之上
                $time = date_create(date(hg_format_date(min($success_time))));
                date_add($time,date_interval_create_from_date_string($expire_time.' months'));
                if($expire_time==-1){
                    $expire = -1;
                } elseif(time()>min($success_time) && time()< strtotime(date_format($time,'Y-m-d H:i:s'))){
                    $expire = strtotime(date_format($time,'Y-m-d H:i:s'));
                }
            }else {
                $expire = 0;
            }
        }
        return $expire;
    }

    /**
     * 获取版本购买详情数据
     */
    public function getShopVersionDetail(){
        $this->validateWithAttribute([
            'shop_id'   => 'required|alpha_dash',
        ],[
            'shop_id'   => '店铺id'
        ]);
        $version_order = VersionOrder::where('shop_id',request('shop_id'))->get(['shop_id','product_id','product_name','sku','total','order_no','success_time']);
        if(!$version_order->isEmpty()){
            foreach ($version_order as $item) {
                $item->sku = $item->sku ? unserialize($item->sku) : [];
                $years = config('define.year_time.' . $item->sku['properties'][0]['v']);
                $time = date_create(date(hg_format_date($item->success_time)));
                date_add($time,date_interval_create_from_date_string($years.'months'));
                $item->success_time = hg_format_date($item->success_time);
                $item->expire_time = date_format($time,'Y-m-d H:i:s');
                $item->type = $item->order_no == -1 ? 0 : 1;    //类型，1-购买，0-手动设置
                $item->makeHidden(['order_no']);
            }
        }
        return $this->output($version_order);
    }

    /**
     * 活跃用户列表
     */
    public function activeUserList(){
        $this->validateWith([
            'page'       => 'numeric',
            'count'      => 'numeric|max:10000',
            'name'       => 'alpha_dash|max:64',
            'email'      => 'max:64',
            'mobile'     => 'max:64',
            'order'      => 'alpha_dash|in:desc,asc'
        ]);
        $count = request('count') ?: 15;
        $users = Users::select('users.id', 'name', 'email', 'mobile', 'users.created_at','login_time','search_word', 'search_engine');
        if (request('name')) {
            $users->where('name', 'like', '%' . request('name') . '%');
        }
        if (request('email')) {
            $users->where('email', 'like', '%' . request('email') . '%');
        }
        if (request('mobile')) {
            $users->where('mobile', 'like', '%' . request('mobile') . '%');
        }
        $users->where('version', VERSION_ADVANCED);
        $users->leftJoin('user_shop','users.id','user_shop.user_id');
        $users->leftJoin('shop','shop.hashid','user_shop.shop_id');
        $users->leftJoin('regist_track', 'users.id', '=', 'regist_track.user_id');
        $users->addSelect('shop.agent','shop.channel');
        request('order') && $users->orderBy('login_time',request('order'));
        $page = $users->whereBetween('login_time',[strtotime(date('Y-m-d 00:00:00')),time()])
            ->whereNotBetween('users.created_at', [date('Y-m-d 00:00:00'),hg_format_date()])
            ->orderBy('login_time', 'desc')->paginate($count);
        if ($page->items()) {
            foreach ($page->items() as $item) {
                $item->login_time = $item->login_time ? hg_format_date($item->login_time) : '';
                $item->shop_id = $item->shop ? $item->shop->shop_id : '';
            }
        }
        return $this->output($this->listToPage($page));
    }

    /**
     * 获取用户点击购买按钮记录
     */
    public function userButtonClickList(){
        $this->validateWithAttribute([
            'type'  => 'alpha_dash|in:advanced,fullplat',
        ],[
            'type'  => '类型'
        ]);
        $count = request('count') ? : 20;
        $where = [];
        if(request('type')){
            $where = [
                'type'  => request('type')
            ];
        }
        $list = UserButtonClicks::where($where)->orderByDesc('click_time')->groupBy('user_id')->groupBy('type')->paginate($count);
        if($list->items()){
            foreach ($list->items() as $key=>$value){
                $where = ['user_id'=>$value->user_id,'type'=>$value->type];
                $value->click_time = hg_format_date(UserButtonClicks::where($where)->orderByDesc('click_time')->value('click_time'));
                $value->user_name = $value->user ? $value->user->name : '';
                $value->mobile = $value->user ? trim($value->user->mobile) : '';
                $value->create_time = $value->user ? $value->user->created_at : '';
                $value->version = $value->shop ? $value->shop->version : VERSION_BASIC;
                $value->total_click = UserButtonClicks::where($where)->count();
                $value->today_click = UserButtonClicks::where($where)->whereBetween('click_time',[strtotime(date('Y-m-d 00:00:00')),time()])->count();
                $value->makeHidden(['user','shop']);
            }
        }
        return $this->output($this->listToPage($list));
    }


    /**
     * 所有付费用户
     */
    public function todayPayUser(){

        $count = request('count') ? : 20;
        $shop = VersionOrder::
//        whereBetween('create_time',[strtotime(date('Y-m-d 00:00:00')),time()])->
            groupBy('shop_id')->orderByDesc('create_time')
            ->paginate($count,['shop_id','success_time as order_time']);
        if($shop->items()){
            foreach ($shop->items() as $item) {
                $user = $item->shopUser($item->shop_id);
                $item->id = intval($user['id']);
                $item->name = trim($user['name']);
                $item->email = trim($user['email']);
                $item->mobile =trim($user['mobile']);
                $item->created_at = $user['created_at'];
                $item->login_time = hg_format_date($user['login_time']);
                $item->order_time = hg_format_date($item->order_time);
                $item->channel = $user['channel'];
                $item->agent = $user['agent'];
                $item->search_word = $user['search_word'];
                $item->search_engine = $user['search_engine'];

            }
        }
        return $this->output($this->listToPage($shop));
    }

    /**
     * 获取当前管理员等级
    */
    public function level()
    {
        $userId = Auth::id();
        $config = config('define');
        $commonUserIds = $config['admin_signature_id'];
        $superUserIds = $config['admin_super_id'];
        $data = [
            'level' => 'user'
        ];
        if(in_array($userId,$superUserIds)){
            $data['level'] = 'super';
        }elseif(in_array($userId,$commonUserIds)){
            $data['level'] = 'admin';
        }

        return $this->output($data);
    }

}
