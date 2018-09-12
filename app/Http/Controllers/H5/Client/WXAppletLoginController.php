<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/8/20
 * Time: 下午9:40
 */

namespace App\Http\Controllers\H5\Client;

use App\Events\AppEvent\AppMemberEvent;
use App\Http\Controllers\Admin\OpenPlatform\CoreTrait;
use App\Http\Requests\Request;
use App\Jobs\WechatMemberCreate;
use App\Jobs\WechatMemberUpdate;
use App\Models\Member;
use App\Models\OpenPlatformApplet;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class WXAppletLoginController extends BaseController
{
    use CoreTrait;

    public function appletSessionKey()
    {
        $component_access_token = Redis::get($this->keyName('component_access_token'));
        if (!$component_access_token) {
            $component_access_token = $this->getComponentAccessToken();
        }
        $url = config('define.open_platform.wx_applet.api.jscode2session')
            . '?appid=' . request('app_id')
            . '&js_code=' . request('code')
            . '&component_appid=' . config('wechat.open_platform.app_id')
            . '&component_access_token=' . $component_access_token
            . '&grant_type=authorization_code';
        $response = $this->curl_trait('GET', $url);
        $sessionToken = md5($response['session_key'] . $response['openid']);
        $expires_in = isset($response['expires_in']) ? $response['expires_in'] : 7200;
        Cache::put($sessionToken, $response['session_key'], $expires_in / 60);
        return ['expires_in' => $expires_in, 'sessionToken' => $sessionToken];
    }

    public function wxAppletLogin()
    {
        $param = $this->validateAppletLoginParam();
        $user_info = $this->decryptData($param);
        //Todo 会员id生成方式调整 根据unionid来生成，参考秀赞会员
        $u_member = '';
        $uid = '';
        if (isset($user_info['union_id'])) {
            $uid = md5($this->shop['id'] . 'wechat' . $user_info['union_id']);
            $u_member = json_decode(Cache::get('wechat:member:' . $uid), 1);
        }
        $member_id = md5($this->shop['id'] . 'wechat' . $user_info['openId']);
        $member = json_decode(Cache::get('wechat:member:' . $member_id), 1);
        if ($u_member) {
            $this->memberUpdate($u_member, $user_info);
            $this->updateAppletName($param['appid'],$u_member['id']);
            $signature_response = $this->signatureResponse($u_member);
        } elseif ($member && !$u_member) {
            if (isset($user_info['union_id'])) {
                $o_member = Member::where([
                    'union_id' => $user_info['union_id'],
                    'source'   => 'wechat',
                    'shop_id'  => $this->shop['id']
                ])->first()->toArray();
                if ($o_member) {
                    $member = $o_member;
                } else {
                    Member::where([
                        'uid'    => $member_id,
                        'source' => 'wechat'
                    ])->update(['union_id' => $user_info['union_id']]);
                }
                Redis::sadd('wachat:member_id:' . $this->shop['id'] . ':' . $user_info['union_id'], $member_id);
                $member['id'] = $member_id;
            }
            $this->memberUpdate($member, $user_info);
            $this->updateAppletName($param['appid'],$member['id']);
            $signature_response = $this->signatureResponse($member);
        } elseif (!$member && !$u_member) {
            $param = $this->setMemberInfo($user_info, $uid ?: $member_id);
            Member::insert($param);
            $param['id'] = $uid ?: $member_id;
            Cache::forever('wechat:member:' . ($uid ?: $member_id), json_encode($param));
            $this->updateAppletName(request('app_id'),$param['id']);
            $signature_response = $this->signatureResponse($param);
        }
        return $this->output($signature_response);
    }

    private function updateAppletName($app_id,$member_id){
        $diy_name = OpenPlatformApplet::where('appid',$app_id)->value('diy_name');
        $diy_name && Member::where('uid',$member_id)->update(['extra'=>serialize(['appid'=>$app_id,'diy_name'=>$diy_name])]);
    }

    private function validateAppletLoginParam()
    {
        $this->validateWithAttribute([
            'encryptedData' => 'required',
            'sessionToken'  => 'required',
            'iv'            => 'required',
        ], [
            'encryptedData' => '敏感用户信息',
            'sessionToken'  => '用户会话秘钥',
            'iv'            => '加密算法的初始向量',
        ]);
        return [
            'encryptedData' => trim(request('encryptedData')),
            'sessionKey'    => trim(request('sessionKey')),
            'appid'         => trim(request('app_id')),
            'iv'            => trim(request('iv'))
        ];
    }

    /**
     * 敏感用户数据信息处理
     *
     * @param $param
     *
     * @return mixed
     * @throws \App\Exceptions\OutputExpection
     */
    private function decryptData($param)
    {
        if (strlen($param['sessionKey']) != 24) {
            $this->error('illegal_aeskey');
        }
        if (strlen($param['iv']) != 24) {
            $this->error('illegal_vi');
        }

        $aesIV = base64_decode($param['iv']);
        $aesCipher = base64_decode($param['encryptedData']);
        $aesKey = base64_decode($param['sessionKey']);
        $result = $this->decrypt($aesCipher, $aesIV, $aesKey);
        $dataObj = json_decode($result, 1);

        if ($dataObj == null) {
            $this->error('illegal_buffer');
        }
        if ($dataObj['watermark']['appid'] != $param['appid']) {
            $this->error('illegal_buffer');
        }
        return $dataObj;
    }

    /**
     * 解密敏感用户数据信息
     *
     * @param $aesCipher
     * @param $aesIV
     * @param $aesKey
     *
     * @return \App\Controllers\OauthClient\删除填充补位后的明文
     * @throws \App\Exceptions\OutputExpection
     */
    private function decrypt($aesCipher, $aesIV, $aesKey)
    {
        try {
            $decrypted = openssl_decrypt($aesCipher, 'aes-128-cbc', $aesKey, OPENSSL_RAW_DATA, $aesIV);
        } catch (\Exception $e) {
            $this->error('illegal_buffer');
        }
        try {
            $result = $this->decode($decrypted);
        } catch (\Exception $e) {
            $this->error('illegal_buffer');
        }
        return $result;
    }

    /**
     * 对解密后的明文进行补位删除
     *
     * @param decrypted 解密后的明文
     *
     * @return 删除填充补位后的明文
     */
    private function decode($text)
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            $pad = 0;
        }
        return substr($text, 0, (strlen($text) - $pad));
    }

    private function memberUpdate($member, $user_info)
    {
        if (($member['avatar'] != $user_info['avatarUrl']) || ($member['nick_name'] != $user_info['nickName'])) {
            $u_member['avatar'] = $user_info['avatarUrl'];
            $u_member['nick_name'] = (ctype_space($user_info['nickName']) || !$user_info['nickName']) ? DEFAULT_NICK_NAME : $user_info['nickName'];
            Cache::forever('wechat:member:' . $member['id'], json_encode($member));
            dispatch((new WechatMemberUpdate($member['id'], $user_info['avatarUrl'], $user_info['nickName']))->onQueue(DEFAULT_QUEUE));
        }
    }

    private function signatureResponse($member)
    {
        event(new AppMemberEvent($member,$this->shop['id']));
        return $this->signature([
            'id'        => $member['id'],
            'nick_name' => $member['nick_name'],
            'openid'    => $member['openid'],
            'avatar'    => $member['avatar'],
            'source'    => $member['source'],
        ]);
    }

    private function setMemberInfo($user, $member_id)
    {
        return [
            'shop_id'     => $this->shop['id'],//$request->shop_id;
            'source'      => 'applet',
            'openid'      => $user['openId'],//对应微信的 OPENID
            'nick_name'   => $user['nickName'] ?: $user['openId'], // 对应微信的 nickname,如果没有默认值设为openid
            'avatar'      => $user['avatarUrl'] ?: '', // 对应微信的 nickname
            'uid'         => $member_id,
            'create_time' => time(),
            'sex'         => intval($user['gender']),
            'address'     => $user['province'] . $user['city'], // 对应微信的 nickname
            'language'    => isset($user['language']) ? $user['language'] : '',
            'province'    => $user['province'],
            'union_id'    => isset($user['unionid']) ? $user['unionid'] : '',
            'ip'          => hg_getip(),
        ];
    }

    /**
     * 判断用户是否绑定手机号
     */
    public function checkMobileBind(){
        $member_info = request('member');
        if(!isset($member_info['openid'])){
            return $this->error('no_openid');
        }
        $applet_audit_status = $this->checkAppletAuditStatus();
        $shop = Shop::where('hashid',request('shop_id'))->select('fast_login','applet_version')->first();
        $mobile = Member::where(['openid'=>$member_info['openid'],'shop_id'=>request('shop_id')])->value('mobile');
        if(!$mobile && $shop->fast_login && $shop->applet_version!='basic' && !$applet_audit_status){
            return $this->error('mobile_no_bind');
        }else{
            return $this->output($member_info);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 获取用户微信绑定的手机号
     */
    public function getPhoneNumber(){
        $this->validateWithAttribute([
            'encryptedData' => 'required',
            'sessionToken'  => 'required',
            'iv'            => 'required',
        ], [
            'encryptedData' => '敏感用户信息',
            'sessionToken'  => '用户会话秘钥',
            'iv'            => '加密算法的初始向量',
        ]);
        $sessionToken = trim(request('sessionToken'));
        $session_key = Cache::get($sessionToken);
        if (!$session_key) {
            return response([
                'error' => 'NO_SESSION_KEY',
                'message' => trans('validation.NO_SESSION_KEY'),
            ]);
        }
        $param =  [
            'encryptedData' => trim(request('encryptedData')),
            'sessionKey'    => trim($session_key),
            'appid'         => trim(request('app_id')),
            'iv'            => trim(request('iv'))
        ];
        $user_phone = $this->decryptData($param);
        return $this->output($user_phone);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 手机号授权
     */
    public function appletMobileAuthorize(){
        $this->validateWithAttribute(['member'=>'required|array','mobile'=>'required','authorize'=>'required'],['member'=>'用户信息','mobile'=>'手机号','authorize'=>'是否授权']);
        $member_info = request('member');
        $member = Member::where(['openid'=>$member_info['openid'],'shop_id'=>$this->shop['id']])->first();
        if(request('authorize')!=1){
            if(request('code') != Cache::get('mobile:code:'.request('mobile'))){
                $this->error('mobile_code_error');
            }
        }
        $member->mobile = request('mobile');
        $member->save();
        Redis::sadd('mobileBind:'.$this->shop['id'].':'.request('mobile'),$member->uid);
        Redis::sadd('mobileBind:'.$this->shop['id'].':applet:'.request('mobile'),$member->uid);
        return $this->output($member_info);
    }

    /**
     * 判断手机号是否绑定到h5端会员
     */
    public function checkMobileBindH5(){
        $this->validateWithAttribute([
            'mobile'    => 'required|regex:/^(1)[3,4,5,7,8,9]\d{9}$/',
        ],[
            'mobile'    => '手机号'
        ]);
        $mobile = request('mobile');
        $is_exists = Member::where(['mobile'=>$mobile,'source'=>'wechat'])->value('uid');
        return $this->output([
            'is_bind'   => $is_exists ? 1 : 0
        ]);
    }

}
