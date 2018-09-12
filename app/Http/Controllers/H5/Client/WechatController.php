<?php
/**
 * 微信 oauth 网页授权
 */
namespace App\Http\Controllers\H5\Client;

use App\Events\AppEvent\AppMemberEvent;
use App\Jobs\WechatMemberCreate;
use App\Models\Member;
use App\Models\Shop;
use Curl\Curl;
use Doctrine\Common\Cache\PredisCache;
use EasyWeChat\Foundation\Application;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Overtrue\Socialite\AuthorizeFailedException;

class WechatController extends BaseController
{
    public function callback()
    {
        if(!$this->shop['id']){
            return $this->error('shop-id-empty');
        }
        $app = new Application(config('wechat'));
        $oauth = $app->oauth;
        try{
            $user = $oauth->user();
        }
        catch(AuthorizeFailedException $e){
            throw new HttpResponseException($this->errorWithText('wechat_error_'.$e->body['errcode'],$e->body['errmsg']));
        }
        $id = md5($this->shop['id'].'wechat'.$user->getId());
        $member = json_decode(Cache::get('wechat:member:'.$id));
        $orginal = $user->getOriginal();
        $u_member = '';$uid='';
        if(isset($orginal['union_id'])){
            $uid = md5($this->shop['id'].'wechat'.$orginal['union_id']);
            $u_member = json_decode(Cache::get('wechat:member:'.$uid));
        }
        if($u_member){
            $this->checkAvatarAndName($u_member,$user);
            $response = $this->getResponse($uid,$user);
        }elseif(!$member && !$u_member){
            $data = $this->handleMember($uid ? : $id,$user,1);
            Member::insert($data);
            $data['id'] = $uid?:$id;unset($data['uid']);
            Cache::forever('wechat:member:'.($uid?:$id),json_encode($data));
            $response = $this->getResponse($uid?:$id,$user);
        }elseif($member && !$u_member){
            $data = $this->handleMember($id,$user,0);
            Member::where('uid',$id)->update($data);
            $data['id'] = $id;unset($data['uid']);
            Cache::forever('wechat:member:'.$id,json_encode($data));
            Redis::sadd('wachat:member_id:'.$this->shop['id'].':'.$data['union_id'],$id);
            $this->checkAvatarAndName($member,$user);
            $response = $this->getResponse($id,$user);
        }
        // 黑名单检出
        $shopInstance = Shop::where('hashid', $this->shop['id'])->first();
        $this->checkoutBlackMember($shopInstance->id, $response['id']);
        event(new AppMemberEvent($response,$this->shop['id']));
        $signature_response = $this->signature($response);
        return $this->output($signature_response);
    }

    private function handleMember($id,$user,$is_new){
        $orginal = $user->getOriginal();
        $data = [
            'shop_id' => $this->shop['id'],//$request->shop_id;
            'source' => 'wechat',
            'openid' => $user->getId(),//对应微信的 OPENID
            'nick_name' => (ctype_space($user->getNickname()) || !$user->getNickname()) ? DEFAULT_NICK_NAME: $user->getNickname(), // 对应微信的 nickname,如果没有默认值设为openid
            'avatar' => $user->getAvatar() ?: '', // 对应微信的 nickname
            'uid' => $id,
            'sex' => isset($orginal['sex'])?$orginal['sex']:'', // 对应微信的 nickname
            'address' => isset($orginal['province'])?$orginal['province']:''.isset($orginal['city'])?$orginal['city']:'', // 对应微信的 nickname
            'language' => isset($orginal['language'])?$orginal['language']:'',
            'province' => isset($orginal['province'])?$orginal['province']:'',
            'union_id' => isset($orginal['unionid']) ? $orginal['unionid'] : '',
            'login_time' => time(),
            'ip' => hg_getip(),
        ];
        $is_new && $data['create_time'] = time();
        return $data;
    }


    /**
     * 微信jssdk签名
     */
    public function sign(){

        $this->validateWith([
            'url'        => 'required|active_url',
        ]);
        $key = 'jssdk:sign:'.config('wechat.app_id').':'.md5(request('url'));
        $signature = Cache::get($key);
        if($signature) {
            $sign = json_decode($signature,1);
            $sign['expire_time'] = $sign['expire_time'] - time();
            return $this->output($sign);
        }
        $app = new Application(config('wechat'));
        $app->cache = new PredisCache(app('redis')->connection()->client());
        try{
            $signature = $app->js->signature(request('url'));
            $expire = (7200-500)/60;
            $signature['expire_time'] = time() + $expire*60;
            Cache::put($key,json_encode($signature),$expire);
            $signature['expire_time'] = $signature['expire_time'] - time();
            return $this->output($signature);
        }
        catch(AuthorizeFailedException $e){
            throw new HttpResponseException($this->errorWithText('wechat_error_'.$e->body['errcode'],$e->body['errmsg']));
        }
    }


}