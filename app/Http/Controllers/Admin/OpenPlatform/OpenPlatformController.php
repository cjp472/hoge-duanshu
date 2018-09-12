<?php

/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/8/4
 * Time: 上午11:49
 */
namespace App\Http\Controllers\Admin\OpenPlatform;

use App\Http\Controllers\Admin\BaseController;
use App\Models\AppletCommit;
use App\Models\AppletRelease;
use App\Models\AppletSubmitAudit;
use App\Models\AppletUpgrade;
use App\Models\OpenPlatformApplet;
use App\Models\OpenPlatformPublic;
use App\Models\Shop;
use App\Models\UserShop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class OpenPlatformController extends BaseController
{
    use CoreTrait;

    public function authEvent()
    {
        $xml = $this->event();
        switch ($xml->InfoType) {
            case 'authorized':
                break;
            case 'updateauthorized':
                break;
            case 'unauthorized':
                $where = ['appid'=>$xml->AuthorizerAppid];
                OpenPlatformApplet::where($where)->delete();
                OpenPlatformPublic::where($where)->delete();
                AppletCommit::where($where)->delete();
                AppletUpgrade::where($where)->delete();
                AppletRelease::where($where)->delete();
                AppletSubmitAudit::where($where)->delete();
                break;
            default:
                break;
        }
        return 'success';
    }

    public function preAuthUrl()
    {
        //api接口监控处理
        if(request('token')){
            request()->replace([
                'redirect_uri'  => '',
            ]);
        }
        $this->validateWith(['auth_type'=>'numeric|in:1,2,3']);
        $pre_auth_code = $this->getPreAuthCode();
        $url = COMPONENT_LOGIN_PAGE
            . '?component_appid=' . config('wechat.open_platform.app_id')
            . '&pre_auth_code=' . $pre_auth_code
            . '&redirect_uri=' . request('redirect_uri')
            . '&auth_type='.(intval(request('auth_type'))?:3);
        return $this->output(['url' => $url]);
    }

    public function wxCallback()
    {
        $this->validateWithAttribute(['auth_code' => 'required',], ['auth_code' => '授权码']);
        $res = $this->getAuthorizationInfo(request('auth_code'));
        $ret = $this->handleAuthorizationInfo($res);
        return $this->output($ret);
    }

    private function handleAuthorizationInfo($res)
    {
        $appid = $res['authorization_info']['authorizer_appid'];
        Redis::setex($this->keyName('authorizer_access_token') . $appid,
            $res['authorization_info']['expires_in'],
            $res['authorization_info']['authorizer_access_token']);
        $data = $this->getAuthorizerInfo($appid);
        $type = isset($data['authorizer_info']['MiniProgramInfo']) ? 'applet' : 'public';
        switch ($type) {
            case 'applet':
                $open_platform_applet = OpenPlatformApplet::where(['shop_id' => $this->shop['id'], 'appid' => $appid])->first();
                $open_platform_applet_appid = OpenPlatformApplet::where('appid', $appid)->first();
                if (!$open_platform_applet && $open_platform_applet_appid) {
                    return $this->handleError($data['authorizer_info']['nick_name'],
                        $open_platform_applet_appid->shop_id,
                        '一个小程序只能绑定一个短书店铺',
                        'applet_no_repeat_bind');
                }

                $open_platform_applet_shop_id = OpenPlatformApplet::where('shop_id', $this->shop['id'])->first();
                if (!$open_platform_applet && $open_platform_applet_shop_id) {
                    $this->updateAppletData($open_platform_applet_shop_id, $res, $data, true);
                } else {
                    $open_platform_applet
                        ? $this->updateAppletData($open_platform_applet, $res, $data)
                        : $this->createAppletData($res, $data);
                }
                $name = $this->handleFuncInfo($data['authorization_info']['func_info']);
                if ($name) {
                    return $this->handleError($data['authorizer_info']['nick_name'],
                        $open_platform_applet_appid ? $open_platform_applet_appid->shop_id:'',
                        '授权信息不完整，登录小程序后台解除短书外第三方授权',
                        'no_func_info');
                }
                break;
            case 'public':
                $open_platform_public_num = OpenPlatformPublic::where(['shop_id' => $this->shop['id']])->count();
                if ($open_platform_public_num >= 5) {
                    return $this->handleError($data['authorizer_info']['nick_name'], $this->shop['id'], '一个店铺最多绑定五个公众号');
                }

                if (request('is_again')) {
                    OpenPlatformPublic::where('appid', $appid)->delete();
                    $this->createPublicData($res);
                } else {
                    $open_platform_public = OpenPlatformPublic::where('appid', $appid)->first();
                    if ($open_platform_public && ($open_platform_public->shop_id !== $this->shop['id'])) {
                        return $this->handleError($data['authorizer_info']['nick_name'], $open_platform_public->shop_id, '一个公众号只能绑定一个店铺');
                    }

                    $open_platform_public
                        ? $this->updatePublicData($open_platform_public, $res, $data)
                        : $this->createPublicData($res, $data);
                }
                break;
            default:
                break;
        }
        return ['success' => 1];
    }

    // 错误处理

    private function handleError($nick_name, $shop_id, $message, $err = '')
    {
        $err && $error['error'] = $err;
        $error['message'] = $message;
        $error['author'] = $nick_name;
        $error['duanshu'] = UserShop::where('shop_id', $shop_id)->first() ? UserShop::where('shop_id', $shop_id)->first()->user['username'] : '';
        return $error;
    }

    // web 检查是否开通小程序
    // 如果开通小程序则显示小程序码

    private function createAppletData($res, $data)
    {
        $open_platform_applet = new OpenPlatformApplet();
        $open_platform_applet->shop_id = $this->shop['id'];
        $open_platform_applet->appid = $res['authorization_info']['authorizer_appid'];
        $open_platform_applet->primitive_name = $data['authorizer_info']['user_name'];
        $open_platform_applet->diy_name = $data['authorizer_info']['nick_name'];
        $open_platform_applet->access_token = $res['authorization_info']['authorizer_access_token'];
        $open_platform_applet->refresh_token = $res['authorization_info']['authorizer_refresh_token'];
        $open_platform_applet->old_refresh_token = $res['authorization_info']['authorizer_refresh_token'];
        $open_platform_applet->authorizer_info = serialize($data);
        $open_platform_applet->create_time = time();
        $open_platform_applet->is_commit = 0;
        $open_platform_applet->is_domain = 0;
        $open_platform_applet->applet_version = '0.0.0';
        $open_platform_applet->save();
    }

    private function updateAppletData($open_platform_applet, $res, $data, $is_replace = false)
    {
        $open_platform_applet->access_token = $res['authorization_info']['authorizer_access_token'];
        $open_platform_applet->refresh_token = $res['authorization_info']['authorizer_refresh_token'];
        $open_platform_applet->old_refresh_token = $res['authorization_info']['authorizer_refresh_token'];
        $open_platform_applet->authorizer_info = serialize($data);
        $open_platform_applet->update_time = time();
        if ($is_replace) {
            $open_platform_applet->appid = $res['authorization_info']['authorizer_appid'];
            $open_platform_applet->primitive_name = $data['authorizer_info']['user_name'];
            $open_platform_applet->diy_name = $data['authorizer_info']['nick_name'];
            $open_platform_applet->create_time = time();
            $open_platform_applet->update_time = '';
            $open_platform_applet->is_commit = 0;
            $open_platform_applet->is_domain = 0;
        }
        $open_platform_applet->save();

        //清除设置的商户号，小程序高级版降为基础版
        Shop::where('hashid',$open_platform_applet->shop_id)->update(['applet_version'=>'basic']);
        AppletUpgrade::where('shop_id',$open_platform_applet->shop_id)->delete();

    }

    private function createPublicData($res, $data)
    {
        $open_platform_public = new OpenPlatformPublic();
        $open_platform_public->shop_id = $this->shop['id'];
        $open_platform_public->appid = $res['authorization_info']['authorizer_appid'];
        $open_platform_public->primitive_name = $data['authorizer_info']['user_name'];
        $open_platform_public->access_token = $res['authorization_info']['authorizer_access_token'];
        $open_platform_public->refresh_token = $res['authorization_info']['authorizer_refresh_token'];
        $open_platform_public->old_refresh_token = $res['authorization_info']['authorizer_refresh_token'];
        $open_platform_public->authorizer_info = serialize($data);
        $open_platform_public->create_time = time();
        $open_platform_public->save();
    }

    private function updatePublicData($open_platform_public, $res, $data)
    {
        $open_platform_public->access_token = $res['authorization_info']['authorizer_access_token'];
        $open_platform_public->refresh_token = $res['authorization_info']['authorizer_refresh_token'];
        $open_platform_public->old_refresh_token = $res['authorization_info']['authorizer_refresh_token'];
        $open_platform_public->authorizer_info = serialize($data);
        $open_platform_public->update_time = time();
        $open_platform_public->save();
    }

    // 用户解绑

    public function unbind()
    {
        $where = ['appid' => request('appid'), 'shop_id' => $this->shop['id']];
        $public = OpenPlatformPublic::where($where)->first();
        if (!$public) {
            $this->error('no_data');
        }
        $res = $public->delete();
        AppletCommit::where($where)->delete();
        AppletUpgrade::where($where)->delete();
        AppletRelease::where($where)->delete();
        AppletSubmitAudit::where($where)->delete();
        $ret = $res ? ['status' => '1', 'message' => '解绑成功'] : ['status' => '0', 'message' => '解绑失败'];
        return $this->output($ret);
    }

    public function getRedisData()
    {
        switch (request('type')) {
            case 'applet':
                $where = ['shop_id' => request('shop_id') ?: $this->shop['id']];
                $open_platform = OpenPlatformApplet::where($where)->first();
                break;
            case 'public':
                $where = [
                    'shop_id' => request('shop_id') ?: $this->shop['id'],
                    'appid'   => request('appid'),
                ];
                $open_platform = OpenPlatformPublic::where($where)->first();
                break;
        }
        return [
            'component_verify_ticket' => Redis::get($this->keyName('component_verify_ticket')),
            'component_access_token'  => Redis::get($this->keyName('component_access_token')),
            'authorizer_access_token' => Redis::get($this->keyName('authorizer_access_token') . $open_platform->appid),
        ];
    }
}
