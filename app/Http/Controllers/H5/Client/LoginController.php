<?php
/**
 * 叮当会员登录
 */
namespace App\Http\Controllers\H5\Client;


use App\Jobs\WechatMemberCreate;
use App\Models\Member;
use App\Models\MemberBind;
use App\Models\PrivateSettings;
use App\Models\PrivateUser;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;


class LoginController extends BaseController
{
    public function memberLogin()
    {
        $this->validateWithAttribute(['mark'=>'required|array'],['mark'=>'会员标识']);
        $info = request('mark');
        $id = md5(request('shop_id').'app'.$info['telephone']);
        $member = json_decode(Cache::get('wechat:member:'.$id),1);
        if(!$member){
            $data = $this->handleMember($id,$info);
            Member::insert($data);
            $data['id'] = $id;unset($data['uid']);
            Cache::forever('wechat:member:'.$id,json_encode($data));
        }else{
            $data = $this->handleMember($id,$member);
        }
        $response = $this->handResponse($id,$data);
        $signature_response = $this->signature($response);
        return $this->output($signature_response);
    }

    private function handleMember($id,$user){
        return [
            'shop_id' => $user['shop_id']?:'',
            'source' => 'app',
            'openid' => isset($user['telephone'])?$user['telephone']:(isset($user['memberId'])?$user['memberId']:''),//会员的唯一标识
            'nick_name' => isset($user['userName']) ?$user['userName']: (isset($user['nick_name'])?$user['nick_name']:''),
            'avatar' => isset($user['avatarUrl']) ?$user['avatarUrl']: (isset($user['avatar'])?$user['avatar']:''),
            'uid' => $id,
            'mobile' => isset($user['telephone'])?$user['telephone']:(isset($user['mobile'])?$user['mobile']:''),
            'create_time' => time(),
            'sex' => isset($user['sex'])?$user['sex']:'',
            'address' => isset($user['address'])?$user['address']:'',
            'language' => isset($user['language'])?$user['language']:'',
            'province' => isset($user['province'])?$user['province']:'',
            'union_id' => '',
            'ip' => hg_getip(),
        ];
    }

    private function handResponse($id,$data)
    {
        return [
            'id'            => $id,
            'nick_name'     => $data['nick_name']?: '',
            'openid'        => $data['openid']?:'',
            'avatar'        => $data['avatar']?:'',
            'source'        => 'app',
        ];
    }


    /**
     * 获取店铺私密账号设置状态信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPrivateSettings(){
        $applet_audit_status = $this->checkAppletAuditStatus();
        $this->validateWithAttribute([
            'shop_id'   => 'required|alpha_dash'
        ],[
            'shop_id'   => '店铺id'
        ]);
        $shop = Shop::where('hashid',request('shop_id'))->value('id');
        if(!$shop){
            $this->error('no-shop');
        }
        $private_settings = PrivateSettings::where('shop_id',request('shop_id'))->first();
        if(!$private_settings || $applet_audit_status){
            $return = [
                'is_private'    => 0,
                'login_image'   => [],
            ];
        }else{
            $return = [
                'is_private'      => intval($private_settings->is_private),
                'login_image'     => $private_settings->login_image ? unserialize($private_settings->login_image) : []
            ];
        }
        return $this->output($return);
    }

    /**
     * 私密会员登录验证接口
     */
    public function userLogin(){
        $this->validateWithAttribute([
            'username'  => 'required|alpha_num|max:64',
            'password'  => 'required|alpha_num|min:6'
        ],[
            'username'  => '用户名',
            'password'  => '密码'
        ]);
        
        $member = Member::where(['openid'=>request('username'),'shop_id'=>$this->shop['id']])->first();
        if (!$member || !Hash::check(request('password'),$member->password)) {
            return $this->error('login_fail');
        }
//        $login_status = Cache::get('member:status:'.$member->uid);
//        if($login_status){
//            return $this->error('account-already-login');
//        }
        //更新登录时间
        $member->login_time = time();
        $member->save();
        $response = $this->signature([
            'id'            => $member->uid,
            'nick_name'     => trim($member->nick_name),
            'openid'        => trim($member->openid),
            'avatar'        => $member->avatar ? : '',
            'source'        => $member->source,
        ],0);
        //设置缓存，踢出之前登录的账号
        Cache::put('member:status:'.$member->uid,$response['signature'],MEMBER_EXPIRE/60);
        // 黑名单检出
        $shopInstance = Shop::where('hashid',$this->shop['id'])->first();
        $this->checkoutBlackMember($shopInstance->id, $member->uid);
        return $this->output($response);
    }

    /**
     * 退出登录
     */
    public function userLogout()
    {
        Cache::forget('member:status:'.$this->member['id']);
        return $this->output(['success'=>1]);
    }
}