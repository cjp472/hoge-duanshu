<?php

namespace app\Http\Controllers\App\Auth;


use App\Events\AppEvent\AppMemberEvent;
use App\Http\Controllers\App\InitController;
use App\Jobs\WechatMemberCreate;
use App\Jobs\WechatMemberUpdate;
use App\Models\Member;
use App\Models\MemberBind;
use App\Models\ShopApp;
use EasyWeChat\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

class LoginController extends InitController
{

    /**
     * 短书app请求登录如果是新用户，进行注册，如果已经注册，直接返回，目前未使用
     */
    public function applogin(){
        $this->validateAppParam([
            'sign'          => 'required|alpha_dash',
            'avatarUrl'     => 'url',
            'userName'      => 'string',
            'telephone'     => 'regex:/^(1)[3,4,5,7,8]\d{9}$/'
        ],[
            'sign'          => '来源平台',
            'userId'        => '第三方平台唯一ID',
            'avatarUrl'     => '头像',
            'userName'      => '用户名',
            'telephone'     => '手机号'
        ]);
        $uid = md5(request('shop_id').request('sign').request('telephone'));
        $u_member = json_decode(Cache::get('wechat:member:'.$uid));
        if($u_member){
            $this->checkAvatarAndName($u_member);
        }else{
            $data = $this->handleMember($uid,1);
            Member::insert($data);
            $data['id'] = $uid;unset($data['uid']);
            Cache::forever('wechat:member:'.$uid,json_encode($data));
        }
        $response = $this->getResponse($uid);
        $signature_response = $this->signature($response);
        return $this->output($signature_response);
    }

    private function handleMember($id,$is_new){
        $data = [
            'shop_id'   => request('shop_id'),
            'source'    => request('sign')?:'app',
            'openid'    => request('userId'),
            'nick_name' => request('userName'),
            'avatar'    => request('avatarUrl') ?: '',
            'uid'       => $id,
            'mobile'    => request('telephone')?:'',
            'sex'       => request('sex')?:'',
            'address'   => request('province')?:''.request('city')?:'',
            'language'  => request('language')?:'',
            'province'  => request('province')?:'',
            'union_id'  => request('unionid')?: '',
            'ip'        => hg_getip(),
        ];
        $is_new && $data['create_time'] = time();
        return $data;
    }

    /**
     * 判断用户名和头像是否和数据库一致
     * @param $member
     */
    private function checkAvatarAndName($member)
    {
        if((isset($member->avatar) && $member->avatar != request('avatarUrl')) || (isset($member->nick_name) && $member->nick_name != request('userName'))){
            $member->avatar = request('avatarUrl');
            $member->nick_name = request('userName');
            Cache::forever('wechat:member:'.$member->id,json_encode($member));
            dispatch(new WechatMemberUpdate($member->id,request('avatarUrl'),request('userName')));
        }
    }

    protected function getResponse($id)
    {
        return [
            'id'            => $id,
            'nick_name'     => request('userName')?:'',
            'openid'        => request('userId')?:'',
            'avatar'        => request('avatarUrl')?:'',
            'source'        => request('sign')?:'app',
        ];


    }

    /**
     * 手机号注册会员
     * @return Member
     */
    public function mobileRegister(){
        $this->validateAppParam(['username'=>'required|max:11','password'=>'required|alpha_num|min:6'],
            ['username' => '手机号','password' => '密码']);
        $uid = md5(request('shop_id').'app'.request('username'));
        $existMember = Member::where('uid',$uid)->first();
        if(!$existMember){ //没有注册
        $data = $this->validateMember($uid);
        $member = new Member();
        $member->setRawAttributes($data);
        $member->saveOrFail();
        $data['id'] = $uid;unset($data['uid']);
        Cache::forever('wechat:member:'.$uid,json_encode($data));
        Redis::sadd('mobileBind:'.request('shop_id').':'.request('username'),$uid);
            $unionMember = Member::where('mobile',request('username'))
            ->where('source','<>','app')
            ->select('union_id','openid','id','shop_id','source','uid')
            ->first();
        if($unionMember){ //记录存在，执行绑定操作
            if(!$unionMember->union_id) {  //union_id不存在
                $app = new Application(config('wechat'));
                $userServe = $app->user;
                $weChatData = $userServe->get($unionMember->openid);
                $unionid = $weChatData->unionid;
                $unionMember->union_id = $unionid;
                $unionMember->save();      //更新member表的union_id
            }
            $union_id = $unionMember->union_id ? : $unionid;
            $mobile_unionid = md5(request('shop_id').request('username').$union_id);
            MemberBind::insert([['uid'=>$unionMember->uid,'union_mobile'=>$mobile_unionid],
                ['uid'=>$uid,'union_mobile'=>$mobile_unionid]]);
            return $this->getResponseData($uid,$mobile_unionid,'0','');
        }else{ //来源是wechat记录不存在
            return $this->getResponseData($uid,0,'0','');
            }
        }else{
            return response()->json([
                'error_code'    => 'already_register',
                'error_message' => trans('validation.already_register'),
            ]);
        }
    }

    //接收参数，手机号注册
    private function validateMember($uid){
        $data = [
            'shop_id'    => request('shop_id'),
            'source'     => 'app',
            'openid'     => request('username'),
            'nick_name'  => request('nick_name') ? : '',
            'uid'        => $uid,
            'password'   => bcrypt(request('password')),
            'mobile'     => request('username'),
            'create_time'=> time(),
        ];
        return $data;
    }

    //返回接口，手机号注册
    private function getResponseData($uid,$bind,$code,$message){
        $data = [
            'error_code'    => $code,
            'error_message' => $message,
            'result'        =>[
                'uid' =>  $uid,
                'username' =>request('username') ? : '',
                'avatar'    => '',
                'nick_name' => request('nick_name') ? : '',
            ],
        ];
        $bind && $data['result']['bind_unionid'] = $bind;
        return $data;
    }

    /**
     * 会员手机号登录
     * @return \Illuminate\Http\JsonResponse
     */
    public function mobileLogin()
    {
        $this->validateAppParam([
            'username'   => 'required|string',
            'password'   => 'required|alpha_num|min:6',
            'platform'   => 'alpha_dash'
        ],['username'=>'用户名','password'=>'密码','platform'=>'平台']);
        $member = Member::where('openid',request('username'))->first();
        if (!$member || !Hash::check(request('password'),$member->password)) {
            return response()->json([
                'error_code'    => 'login-fail',
                'error_message' => trans('validation.login_fail'),
            ]);
        }
//        if ($member->is_black == 1) {
//            $this->error('black');
//        }
        $union = MemberBind::where('uid',$member->uid)->value('union_mobile');
        return response()->json([
            'error_code'    => '0',
            'error_message' => '',
            'result'        => [
                'uid'    => $member->uid,
                'username'     => $member->openid,
                'avatar'       => $member->avatar,
                'nick_name'    => $member->nick_name,
                'bind_unionid' => $union ? : '',

            ]
        ]);
    }

    /**
     * 修改密码
     * @return mixed
     */
    public function updatePassword(){
        $this->validateAppParam(
            ['uid'=>'required|alpha_dash','old_password'=>'required|alpha_num|min:6','password' => 'required|alpha_num|min:6'],
            ['uid' => '用户账号','old_password'=>'原密码','password' => '现在密码']
        );
        $member = Member::where('uid' , request('uid'))->first();
        if($member){
            if(!Hash::check(request('old_password'),$member->password)){
                return response()->json([
                    'error_code'    => 'wrong_password',
                    'error_message' => trans('validation.wrong_password'),
                ]);
            }
            $member->password = bcrypt(request('password'));
            $member->saveOrFail();
            $response = [
                'error_code'    => '0',
                'error_message' => '',
            ];
        }else{
            $response = [
                'error_code'    => 'no-user-info',
                'error_message' => trans('validation.no-user-info'),
            ];
        }

        return response()->json($response);
    }



}