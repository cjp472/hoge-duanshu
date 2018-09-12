<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/7/3
 * Time: 16:05
 */

namespace App\Http\Controllers\H5\Client;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\AppEvent\AppMemberEvent;
use App\Jobs\WechatMemberCreate;
use App\Jobs\WechatMemberUpdate;
use App\Models\Member;
use App\Models\OpenPlatformApplet;
use App\Models\Shop;
use Doctrine\Common\Cache\PredisCache;
use EasyWeChat\Core\Exception;
use EasyWeChat\Foundation\Application;
use GuzzleHttp\Client;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class WechatAppletController extends BaseController
{

    /**
     * 微信小程序sessionKey流程
     * @return array
     */
    public function appletSessionKey()
    {

        if(request('code') == 'the code is a mock one'){
            return ['expires_in' => 0, 'sessionToken' => request('code')];
        }
        $config = $this->getConfig();
//        $response = $this->getSessionKey($config);
//        $sessionToken = md5($response['session_key'].$response['openid']);
//        $expires_in = $response['expires_in'] ?: 7200;
//        Cache::put($sessionToken,$response['session_key'],$expires_in/60);
//        return ['expires_in' => $expires_in, 'sessionToken' => $sessionToken];


        $app = new Application($config);
        $app->cache = new PredisCache(app('redis')->connection()->client());
        $miniProgram = $app->mini_program;
        try{
            $param = $miniProgram->sns->getSessionKey(request('code'));
        } catch(Exception $e){
            throw new HttpResponseException($this->errorWithText('wechat_error_'.$e->getCode(),$e->getMessage()));
        }
        $sessionToken = md5($param->get('session_key').$param->get('openid'));
        $expires_in = $param->get('expires_in') ?: 7200;
        Cache::put($sessionToken,$param->get('session_key'),$expires_in/60);
        return ['expires_in' => $expires_in, 'sessionToken' => $sessionToken];
    }


    /**
     * 处理配置信息
     * @return mixed
     */
    private function getConfig(){
        $config = config('wechat');
        if(request('app_id')){
            $config['mini_program']['app_id'] = trim(request('app_id'));
            $config['mini_program']['secret'] = isset($config['mini_accounts'][trim(request('app_id'))]) ? $config['mini_accounts'][trim(request('app_id'))] :'';
        }
        return $config;
    }
    /**
     * 开放平台获取sessionKey
     * @param $config
     * @return mixed
     */
    private function getSessionKey($config){
        $app = new Application($config);
        try {
            $component_access_token = $app->open_platform->access_token->getTokenFromServer();
            $client = new Client([
                'body' => json_encode([
                    'appid' => $config['mini_program']['app_id'],
                    'js_code' => request('code'),
                    'grant_type' => 'authorization_code',
                    'component_appid' => $config['open_platform']['app_id'],
                    'component_access_token' => $component_access_token,
                ]),
            ]);
            $jscode2session_url = $config['api']['jscode2session'];
            $response = $client->request('GET', $jscode2session_url);
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'mini_programs'));
            $this->error('GET_SESSIONKEY_FAIL');
        }
        $result = json_decode($response->getBody()->getContents(),1);
        event(new CurlLogsEvent($result,$client,$jscode2session_url));
        return $result;


    }

    /**
     * 微信小程序登录
     */
    public function wxAppletLogin(){
        if(request('sessionToken') == 'the code is a mock one'){
            return $this->defaultMemberInfo();
        }
        $this->validateAppletLoginParam();
        $config = $this->getConfig();
        $app = new Application($config);
//        $app = new Application(config('wechat'));
        $app->cache = new PredisCache(app('redis')->connection()->client());
        $miniProgram = $app->mini_program;
        try{
            $user_info = $miniProgram->encryptor->decryptData(request('sessionKey'),request('iv'),request('encryptedData'));
        } catch(Exception $e){
            throw new HttpResponseException($this->errorWithText('wechat_error_'.$e->getCode(),trans('validation.'.$e->getMessage())));
        }
        //Todo 会员id生成方式调整 根据unionid来生成，参考秀赞会员
        $u_member = ''; $uid = '';
        $shopInstance = Shop::where('hashid', $this->shop['id'])->first();
        if(isset($user_info['union_id'])){
            $uid = md5($this->shop['id'].'wechat'.$user_info['union_id']);
            $u_member = json_decode(Cache::get('wechat:member:'.$uid),1);
        }
        $member_id = md5($this->shop['id'].'wechat'.$user_info['openId']);
        $member = json_decode(Cache::get('wechat:member:'.$member_id),1);
        if($u_member){
            $this->memberUpdate($u_member,$user_info);
            $this->updateAppletName(request('app_id'),$u_member['id']);
            // 黑名单检出
            $this->checkoutBlackMember($shopInstance->id, $u_member['id']);
            $signature_response = $this->signatureResponse($u_member);
        }elseif($member && !$u_member) {
            if (isset($user_info['union_id'])) {
                $o_member = Member::where(['union_id' => $user_info['union_id'], 'source' => 'wechat', 'shop_id' => $this->shop['id']])->first()->toArray();
                if ($o_member) {
                    $member = $o_member;
                } else {
                    Member::where(['uid' => $member_id, 'source' => 'wechat'])->update(['union_id' => $user_info['union_id']]);
                }
                Redis::sadd('wachat:member_id:'.$this->shop['id'].':' . $user_info['union_id'], $member_id);
                $member['id'] = $member_id;
                
            }
            $this->memberUpdate($member,$user_info);
            $this->updateAppletName(request('app_id'),$member['id']);
            // 黑名单检出
            $this->checkoutBlackMember($shopInstance->id, $member['id']);
            $signature_response = $this->signatureResponse($member);
        }elseif(!$member && !$u_member){
            $param = $this->setMemberInfo($user_info, $uid ? : $member_id);
            Member::insert($param);
            $param['id'] = $uid?:$member_id;
            $this->updateAppletName(request('app_id'),$param['id']);
            Cache::forever('wechat:member:' . ($uid?:$member_id), json_encode($param));
            $signature_response = $this->signatureResponse($param);
        }
        return $this->output($signature_response);
    }

    private function updateAppletName($app_id,$member_id){
        $diy_name = OpenPlatformApplet::where('appid',$app_id)->value('diy_name');
        $diy_name && Member::where('uid',$member_id)->update(['extra'=>serialize(['appid'=>$app_id,'diy_name'=>$diy_name])]);
    }

    private function signatureResponse($member){

        event(new AppMemberEvent($member,$this->shop['id']));
        $signature_response = $this->signature([
            'id'            => $member['id'],
            'nick_name'     => $member['nick_name'],
            'openid'        => $member['openid'],
            'avatar'        => $member['avatar'],
            'source'        => $member['source'],
        ]);
        return $signature_response;
    }

    private function memberUpdate($member,$user_info){
        if(($member['avatar'] != $user_info['avatarUrl']) || ($member['nick_name'] != $user_info['nickName'])){
            $u_member['avatar'] = $user_info['avatarUrl'];
            $u_member['nick_name'] = $user_info['nickName'];
            Cache::forever('wechat:member:'.$member['id'],json_encode($member));
            dispatch((new WechatMemberUpdate($member['id'],$user_info['avatarUrl'],$user_info['nickName']))->onQueue(DEFAULT_QUEUE));
        }
    }

    private function validateAppletLoginParam(){
        $this->validateWithAttribute([
            'encryptedData' => 'required',
            'sessionToken'    => 'required',
            'iv'            => 'required',
        ],[
            'encryptedData' => '敏感用户信息',
            'sessionToken'    => '用户会话秘钥',
            'iv'            => '加密算法的初始向量',
        ]);

    }

    private function setMemberInfo($user,$member_id){
        return [
            'shop_id' => $this->shop['id'],//$request->shop_id;
            'source' => 'applet',
            'openid' => $user['openId'],//对应微信的 OPENID
            'nick_name' => $user['nickName'] ?: $user['openId'], // 对应微信的 nickname,如果没有默认值设为openid
            'avatar' => $user['avatarUrl'] ?: '', // 对应微信的 nickname
            'uid' => $member_id,
            'create_time' => time(),
            'sex' => intval($user['gender']),
            'address' => $user['province'].$user['city'], // 对应微信的 nickname
            'language' => isset($user['language']) ? $user['language'] : '',
            'province' => $user['province'],
            'union_id' => isset($user['unionid']) ? $user['unionid'] : '',
            'login_time' => time(),
            'ip' => hg_getip(),
        ];
    }

    /**
     * 测试环境小程序授权使用默认会员信息
     * @return \Illuminate\Http\JsonResponse
     */
    private function defaultMemberInfo(){
        $member = Member::where('shop_id',request('shop_id'))->orderByDesc('create_time')->first();
        $signature_response = $this->signature([
            'id'            => $member->uid,
            'nick_name'     => $member->nick_name,
            'openid'        => $member->open_id,
            'avatar'        => $member->avatar,
            'source'        => $member->source,
        ]);
        return $this->output($signature_response);
    }
}
