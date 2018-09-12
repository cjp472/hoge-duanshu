<?php

/**
 * 用户管理
 */
namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Member;
use App\Models\PrivateSettings;
use App\Models\PrivateUser;
use App\Models\Shop;
use App\Models\TryUser;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use App\Models\VersionExpire;
use App\Models\MemberGroupMembers;
use App\Models\Order;

class UserController extends BaseController
{
    /**
     * 用户列表
     */
    public function lists()
    {
        $this->validateWithAttribute([
            'page'       => 'numeric',
            'count'      => 'numeric|max:10000',
            'consume'    => 'numeric|in:0,1',
            'nick_name'  => 'max:64',
            'keyword'    => 'max:64',
            'tel'        => 'regex:/^(1)[3,4,5,7,8]\d{9}$/',
            'start_time' => 'date',
            'end_time'   => 'date',
            'order'      => '',
            'source'     => 'alpha_dash',
            'have_contact' => 'bool',
            'order_content_type' => ''
        ],[
            'page'       => '页数',
            'count'      => '每页数目',
            'consume'    => '消费状态',
            'nick_name'  => '昵称',
            'tel'        => '手机号',
            'start_time' => '搜索开始时间',
            'end_time'   => '搜索结束时间',
            'order'      => '排序',
            'source'     => '用户来源',
            'order_content_type' => '订单内容类型',
            'have_contract' => '有无联系方式'
        ]);
        $this->shopInstance = Shop::where('hashid', $this->shop['id'])->firstOrFail();
        $default_count = 10;
        $count = request('count') ? : $default_count;
        $sql = $this->filterMember();
        $paginator = $sql->paginate($count);
        $data = $this->listToPage($paginator);
        $members_id = [];
        foreach ($data['data'] as $item) {
            $members_id[] = $item['id'];
        }
        $mg = $this->membersGroup($members_id);
        foreach ($data['data'] as $item) {
            if($item->source == 'inner'){
                $item->nick_name = $item->nick_name ? : $item->openid;
            }
            // if($item->mobile && (Redis::scard('mobileBind:'.$item->shop_id.':'.$item->mobile) > 1) && in_array($item->uid,Redis::smembers('mobileBind:'.$item->shop_id.':'.$item->mobile))){
            //     $item->source = '/';
            // } 表示绑定了小程序和h5等多个source。产品调整注释掉
            $item->create_time = $item->create_time ? date('Y-m-d H:i:s',$item->create_time) : '';
            $item->login_time = $item->login_time ? date('Y-m-d H:i:s', $item->login_time) : '';
            $item->birthday = $item->birthday ? date('Y-m-d',$item->birthday) : '';
            $item->extra = $item->extra?unserialize($item->extra):[];
            
            
            $item->groups = array_key_exists($item->id, $mg) ? $mg[$item->id] : [];
            $item->makeVisible(['sex','email','source','birthday','extra','amount','create_time']);
            $item->setKeyType('string');
            $item->pk = $item->id;
            $item->id = $item->uid;
            $item->mobile = $item->mobile ? $item->mobile : '';
        }
        // dd($data);
        return $this->output($data);

    }

    /**
     * 获取会员列表数据
     * @param int $default_count
     * @return array
     */
    private function filterMember(){
        $member = Member::where('shop_id',$this->shop['id']);
        if(request('consume') == 1){
            $member = $member->where('amount','!=',0);
        }elseif( array_key_exists('consume',request()->input()) && request('consume') == 0){
            $member = $member->where('amount','=',0);
        }
        if(request('nick_name')){
            $member = $member->where('nick_name','like','%'.request('nick_name').'%');
        }
        $keyword = request('keyword');
        if($keyword){
            $member  = $member->where(function ($query) use ($keyword) {
                $query->where('nick_name','like','%'.$keyword.'%')->orWhere(['mobile' => $keyword]);
            });
        }
        if(request('tel')){
            $member  = $member->where('mobile',request('tel'));
        }
        if(!is_null(request('have_contact'))) {
            if(request('have_contact')) {
                $member = $member->where('mobile', '!=', '');
            } else {
                $member = $member->where('mobile', '=', '');
            }
        }
        if(request('source')){
            $member = $member->where('source',request('source'));
        }

        // if(request('order_content_type')) {
        //     $shopId = $this->shop['id'];
        //     switch (request('order_content_type')) {
        //         case 'common':
        //             break;
        //         case 'column':
        //             $order_members_id = Order::columnContentOrder($shopId)->select('user_id')->get()->pluck('user_id')->unique();
        //             break;
        //         case 'course':
        //             $order_members_id = Order::courseContentOrder($shopId)->select('user_id')->get()->pluck('user_id')->unique();
        //             break;
        //         case 'community':
        //             $order_members_id = Order::communityContentOrder($shopId)->select('user_id')->get()->pluck('user_id')->unique();
        //             break;
        //         case 'member_card':
        //             $order_members_id = Order::membercardContentOrder($shopId)->select('user_id')->get()->pluck('user_id')->unique();
        //             break;
        //         case 'offlinecourse':
        //             $order_members_id = Order::offlineCourseContentOrder($shopId)->select('user_id')->get()->pluck('user_id')->unique();
        //             break;
        //         default:
        //             $order_members_id = [];
        //             break;
        //     }
        // }

        
        // $order = 'create_time';   //设置直播管理员，显示列表排序
        // $adesc = 'desc';
        $order_query_string = request('order','create_time');
        $order_query = explode(':', $order_query_string);
        $orderby = $order_query[0];
        $adesc = array_key_exists(1, $order_query) && in_array($order_query[1],['desc','asc']) ? $order_query[1] : '';
        switch ($orderby) {
            case 'live_manage':
                $order_sql = DB::raw('CONVERT(nick_name USING gbk)');
                $adesc = $adesc ? : 'asc';
                break;
            case 'amount':
                $order_sql = 'amount';
                $adesc = $adesc ? : 'desc';
                break;
            case 'login_time':
                $order_sql = 'login_time';
                $adesc = $adesc ? : 'desc';
                break;
            default:
                $order_sql = 'create_time';
                $adesc = $adesc ? : 'desc';
                break;
        }
        if(request('order_content_type')) {
            $contentTypes = explode(',', request('order_content_type'));
            $shopHashId = $this->shopInstance->hashid;
            $member = $member->whereIn('uid', function($query) use($shopHashId, $contentTypes){
                // $channel = env('APP_ENV') == 'production' ? 'production' : 'pre';
                // ->where('channel', $channel)
                $query->select('user_id')->from('order')->where(['shop_id' => $shopHashId, 'pay_status' => 1])->whereIn('content_type', $contentTypes)->groupBy('user_id')->havingRaw('sum(price) > 0');
            });
        }

        if (request('group')) {
            $groupsId = explode(',', request('group'));
            $shopPk = $this->shopInstance->id;
            $member = $member->whereIn('id',function($query) use ($groupsId, $shopPk) {
                $query->select('member_id')->from('members_groupmembers')->join('members_group', 'members_groupmembers.group_id','=', 'members_group.id')->whereIn('members_groupmembers.group_id',$groupsId)->where('members_group.shop_id',$shopPk);
            });
        }

        if(request('start_time') || request('end_time')) {
            $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
            $end_time = request('end_time') ? strtotime(request('end_time')) : time();
            $member = $member->whereBetween('create_time', [$start_time, $end_time]);
        }
        $member = $member->whereRaw('id NOT IN (SELECT member_id FROM members_blacklist WHERE shop_id = ?)',[$this->shopInstance->id]);
        // DB::enableQueryLog();
        $member = $member
            ->select('id','openid','uid','shop_id','avatar','source','nick_name','sex','birthday','mobile','extra','amount','create_time','true_name','email','address','company','login_time')
            ->orderBy($order_sql,$adesc);
        // dd($member->get(),DB::getQueryLog(),$member->toSql());
        return $member;
    }


    public function membersGroup($members_id) {
        $a = MemberGroupMembers::membersGroups($members_id);
        return $a;
    }

    /**
     * 用户详细信息
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function detail($id)
    {
        $member = Member::where(['shop_id' => $this->shop['id'],'uid' => $id])->firstOrFail();
        $member->setKeyType('string');
        $member->birthday = $member->birthday ? date('Y-m-d',$member->birthday) : '';
        $member->makeVisible(['sex','source']);
        //手机号非明文显示
        $member->mobile = $member->mobile ?  : '';
        if($member->source == 'inner') {
            $member->makeVisible(['password']);
            $member->nick_name = $member->nick_name ? : $member->openid;
        }
        $mg = $this->membersGroup([$member->id]);
        $member->groups = array_key_exists($member->id, $mg) ? $mg[$member->id] : [];
        $member->pk = $member->id;
        $member->id = $member->uid;
        return $this->output($member);
    }

    /**
     * 编辑用户
     * @param $id
     * @return mixed
     */
    public function update($id)
    {
        $this->validateWithAttribute([
            'avatar'    => 'url',
            'nick_name' => 'string|max:32',
            'true_name' => 'string|max:32',
            'email'     => 'email',
            'address'   => 'alpha_dash|max:256',
            'company'   => 'alpha_dash|max:64',
            'password'  => 'alpha_num|min:6',
        ],[
            'avatar'    => '头像',
            'nick_name' => '昵称',
            'true_name' => '真名',
            'email'     => '邮箱',
            'mobile'    => '手机号',
            'address'   => '地址',
            'company'   => '公司',
            'password'  => '密码',
        ]);
        $member = Member::where(['shop_id' => $this->shop['id'],'uid' => $id])->firstOrFail();
        $member->avatar = request('avatar') ? : '';
        $member->nick_name = trim(request('nick_name')) ? : '';
        $member->true_name = trim(request('true_name')) ? : '';
        $member->email = trim(request('email'));
        $member->address = trim(request('address'));
        $member->company = trim(request('company'));
        $member->position = trim(request('position'));
        if($member->source == 'inner' && request('password')) {
            $member->password = bcrypt(request('password'));
        }
        $member->saveOrFail();
        $member->setKeyType('string');
        $member->id = $member->uid;
        return $this->output($member);
    }

    /**
     * 注册手机号试用
     * @return mixed
     */
    public function tryUser(){
        $this->validateWithAttribute([
            'mobile'    => 'required|regex:/^1[3,4,5,7,8]\d{9}$/',
            'name'      => 'string|max:32',
            'company'   => 'string|max:64'
        ],[
            'mobile'    => '手机号',
            'name'      => '姓名',
            'company'   => '公司名称'
        ]);

        $is_try_user = TryUser::where('mobile',request('mobile'))->value('id');
        if($is_try_user){
            return $this->error('already-try-user');
        }

        $model = new TryUser();
        $model->setRawAttributes([
            'mobile' => request('mobile'),
            'create_time' => time(),
            'name' => trim(request('name')),
            'company' => trim(request('company'))
        ]);
        $model->save();

        return $this->output(['success' => 1]);
    }

    /**
     * 设置私密账号
     */
    public function setPrivateUser()
    {
        $this->checkPrivateStatus();
        $this->validateWithAttribute([
            'username'  => 'required|alpha_num|min:4',
            'password'  => 'required|alpha_num|min:6',
        ],[
            'username'  => '账户名',
            'password'  => '密码'
        ]);

        $source = 'inner';
        $uid = md5($this->shop['id'].$source.request('username'));
        $is_exists = Member::where('openid',request('username'))->where('shop_id',$this->shop['id'])->value('id');
        if($is_exists){
            $this->error('username-exists');
        }
        $param = [
            'uid'           => $uid,
            'openid'        => request('username'),
            'password'      => bcrypt(request('password')),
            'shop_id'       => $this->shop['id'],
            'create_time'   => time(),
            'source'        => $source,
        ];
        $member = new Member();
        $member->setRawAttributes($param);
        $member->save();
        return $this->output($this->getResponse($member));

    }

    public function setPrivateUserMulit()
    {
        $this->checkPrivateStatus();
        $this->validateWithAttribute([
            'number'    => 'required|numeric|min:1|max:1000',
            'username'  => 'required|alpha_num|min:4',
            'password'  => 'required|alpha_num|min:6',
        ],[
            'number'    => '数目',
            'username'  => '用户名',
            'password'  => '密码',
        ]);

        $total = intval(request('number'));
        $username = trim(request('username'));
        $password = request('password') ? : DEFAULT_PASSWORD;
        $source = 'inner';
        for ($i=1;$i<=$total;$i++){
            $openid = $username . str_pad($i,3,0,STR_PAD_LEFT);
            $param[] = [
                'uid'           => md5($this->shop['id'].$source.$openid),
                'openid'        => $openid,
                'password'      => bcrypt($password),
                'shop_id'       => $this->shop['id'],
                'create_time'   => time(),
                'source'        => $source,
            ];
        }
        $is_exists = Member::where('openid',array_first($param)['openid'])->where('shop_id',$this->shop['id'])->value('id');
        if($is_exists){
            $this->error('username-exists');
        }
        Member::insert($param);
        return $this->output(['success' => 1]);
    }

    /**
     * 文件导入创建私密账号
     */
    public function importPrivateUser(){
        $fileData = $_FILES['file'];
        $name = explode('.',$fileData['name']);
        $type = end($name);
        $back = [];
        $path = resource_path('material/admin/');
        if(!is_dir($path)){
            mkdir($path,0777,1);
        }
        $file = $path.md5(time().$fileData['name']).'.'.$type;
        if(!copy($fileData['tmp_name'],$file)){
            $this->error('load-data-fail');
        }
        $data = Excel::load($file,function($reader){})->getsheet(0)->toArray();
        array_shift($data);
        if($data && is_array($data)){
            $username = [];
            foreach ($data as $k=>$v){
                $this->validateImportParam($v);
                if(array_filter($v) && !Member::where('shop_id',$this->shop['id'])->where('openid',$v[0])->value('id') && !in_array($v[0],$username)) {
                    //账号和密码必须有
                    if($v[0] && $v[1]) {
                        $openid = $v[0];
                        $uid = md5($this->shop['id'] . 'inner' . $openid);
                        $back[] = [
                            'uid' => $uid,
                            'openid' => $openid,
                            'password' => bcrypt($v[1]),
                            'true_name' => trim($v[4]),
                            'mobile'    => trim($v[5]),
                            'email'     => trim($v[6]),
                            'address'   => trim($v[7]),
                            'company'   => trim($v[8]),
                            'shop_id' => $this->shop['id'],
                            'create_time' => $v[2] ? strtotime($v[2]) :time(),
                            'source' => 'inner',

                        ];
                        array_push($username,$openid);
                    }
                }
            }
            if(count($back) > 100){
                $this->error('max-limit-num');
            }
        }
        $back && Member::insert($back);
        @unlink($file);
        if(count(scandir($path))==2) {  //删除空目录，
            rmdir($path);
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 验证导入文件数据
     * @param $data
     */
    private function validateImportParam($data){
        //正则验证用户昵称
        preg_match('/^[A-Za-z0-9]{4,}$/',$data[0],$match_openid);
        !$match_openid && $this->error('import-username-error');
        //正则匹配密码
        preg_match('/^[A-Za-z0-9]{4,}$/',$data[1],$match_password);
        !$match_password && $this->error('import-password-error');
    }


    private function getResponse($data){
        $return = [
            'username'  => $data->openid,
            'shop_id'   => $data->shop_id,
            'create_time'    => hg_format_date($data->create_time),
            'id'            => $data->id,
        ];
        return $return;
    }

    /**
     * 设置店铺私密状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function setShopPrivate(){
        $this->validateWithAttribute([
            'status'    => 'required|numeric|in:0,1'
        ],[
            'status'    => '状态'
        ]);
        $is_private = intval(request('status')) ? 1 : 0;
        $shop = PrivateSettings::where('shop_id',$this->shop['id'])->first();
        if(!$shop){
            $shop = new PrivateSettings();
            $shop->shop_id = $this->shop['id'];
        }
        $shop->is_private = $is_private;
        $shop->saveOrFail();
        Cache::forget('shop:private:settings:'.$this->shop['id']);
        return $this->output(['success'=>1]);
    }

    /**
     * 获取店铺私密账号设置状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function getShopPrivate()
    {
        $shop = PrivateSettings::where('shop_id',$this->shop['id'])->first();
        $is_private = 0;
        $image = [];
        if($shop){
            $is_private = intval($shop->is_private);
            $image = $shop->login_image ? unserialize($shop->login_image):[];
        }
        return $this->output([
            'status' => intval($is_private) ? 1 : 0,
            'login_image' => $image,
        ]);
    }

    /**
     * 修改私密会员设置信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function setPrivateSettings()
    {
        $this->checkPrivateStatus();
        $this->validateWithAttribute([
            'image' => 'required|array',
        ],[
            'image' => '登录封面'
        ]);
        $shop = PrivateSettings::where('shop_id',$this->shop['id'])->first();
        if(!$shop){
            $shop = new PrivateSettings();
            $shop->shop_id = $this->shop['id'];
        }
        $shop->login_image = serialize(request('image'));
        $shop->saveOrFail();
        Cache::forget('shop:private:settings:'.$this->shop['id']);
        return $this->output(['success'=>1]);
    }

    /**
     * 验证是否开启私密会员
     */
    private function checkPrivateStatus(){
        $status = PrivateSettings::where('shop_id',$this->shop['id'])->value('is_private');
        if(!$status){
            $this->error('no-open-private');
        }
    }


    /**
     * 导出用户数据
     */
    public function downloadUser(){

        $this->validateWithAttribute([
            'page' => 'numeric',
            'count' => 'numeric|max:10000',
            'consume' => 'numeric|in:0,1',
            'nick_name' => 'alpha_dash|max:64',
            'keyword' => 'alpha_dash|max:64',
            'tel' => 'regex:/^(1)[3,4,5,7,8]\d{9}$/',
            'start_time' => 'date',
            'end_time' => 'date',
            'order' => '',
            'source' => 'alpha_dash',
            'have_contact' => 'bool',
            'order_content_type' => ''
        ], [
            'page' => '页数',
            'count' => '每页数目',
            'consume' => '消费状态',
            'nick_name' => '昵称',
            'tel' => '手机号',
            'start_time' => '搜索开始时间',
            'end_time' => '搜索结束时间',
            'order' => '排序',
            'source' => '用户来源',
            'order_content_type' => '订单内容类型',
            'have_contract' => '有无联系方式'
        ]);

        $this->shopInstance = Shop::where('hashid', $this->shop['id'])->firstOrFail();
        $members = $this->filterMember()->get();
        foreach ($members as $item) {
            $members_id[] = $item->id;
        }
        $mg = $this->membersGroup($members_id);
        $fields[] = ['账号','用户来源', '昵称', '真实姓名', '手机号','邮箱', '公司', '最近登录时间', '账号创建时间', '消费总金额', '标签'];
        foreach ($members as $key=>$value){
            $fields[] = $this->downloadFormat($value, $mg);
            }
        Excel::create('用户数据'.date('Y-m-d', time()), function($excel) use($fields) {
            $excel->sheet('用户数据', function($sheet) use($fields) {
                $sheet->fromArray($fields,null,'A2',false,false);
            });
        })->export('xls');
    }

    private function downloadFormat($member,$mg)
    {
        $groups = array_key_exists($member->id, $mg) ? $mg[$member->id] : [];
        $groupsName = [];
        foreach ($groups as $value) {
            $groupsName[] = $value['name'];
        }
        return [
            'openid'  => $member->openid,
            'source' => Member::verboseSource($member->source),
            'nick_name'   => hg_emoji_encode($member->nick_name),
            'true_name' => $member->true_name,
            'mobile'    => $member->mobile,
            'email'   => $member->email,
            // 'address'   => $member->address,
            'company'  => $member->company,
            'login_time' => $member->login_time ? hg_format_date($member->login_time) : '',
            'create_time' => $member->create_time ? hg_format_date($member->create_time) : '',
            'amount'    => $member->amount,
            'groups' => join('，', $groupsName)
        ];
    }

    public function getLoginUserInfo()
    {
        $user = hg_user_response();
        $user['shop'] = hg_shop_response(Auth::id());
        return $this->output($user);
    }

}