<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/8/9
 * Time: 上午9:09
 */

namespace App\Http\Controllers\Admin\OpenPlatform;

use App\Http\Controllers\Admin\BaseController;
use App\Http\Controllers\Admin\OpenPlatform\Publics\{
    PublicEventController as Event, PublicTextController as Text
    , PublicImageController as Image
};


class PublicController extends BaseController
{
    use CoreTrait;

    protected $type = 'public';

    public function callback()
    {
        $event = $this->event();
        switch ($event->MsgType) {
            case 'event':
                return (new Event)->handleEvent($event);
                break;
            case 'text':
                 return (new Text)->handleText($event);
                 break;
            case 'image':
                return (new Image)->handleImage($event);
                break;
            default:
                return 'success';
                break;
        }
    }

    public function authorizeUrl()
    {
        $appid = request('appid');
        $redirect_uri = urlencode(request('redirect_uri'));
        $component_appid = config('wechat.open_platform.app_id');
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='
            . $appid . '&redirect_uri='
            . $redirect_uri . '&response_type=code&scope=snsapi_userinfo&state=123456&component_appid=' . $component_appid . '#wechat_redirect';
        return $this->output(['url' => $url]);
    }

    public function getAccessToken()
    {
        $code = request('code');
        $appid = request('appid');
        $component_appid = config('wechat.open_platform.app_id');
        $componentAccessToken = $this->getComponentAccessToken();
        $url = 'https://api.weixin.qq.com/sns/oauth2/component/access_token?appid='
            . $appid . '&code='
            . $code . '&grant_type=authorization_code&component_appid='
            . $component_appid . '&component_access_token=' . $componentAccessToken;
        $accessTokenData = $this->curl_trait('GET', $url);
        return $this->output($accessTokenData);
    }

    public function getUserInfo()
    {
        $access_token = request('access_token');
        $openid = request('openid');
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token='
            . $access_token . '&openid=' . $openid . '&lang=zh_CN';
        $userInfo = $this->curl_trait('GET', $url);
        return $this->output($userInfo);
    }
}
