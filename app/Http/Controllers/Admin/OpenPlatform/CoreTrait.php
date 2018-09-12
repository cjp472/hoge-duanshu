<?php
/**
 * Created by Guhao.
 * User: wzs
 * Date: 17/8/17
 * Time: 下午6:02
 */

namespace App\Http\Controllers\Admin\OpenPlatform;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Http\Controllers\Admin\OpenPlatform\SampleCode\WXBizMsgCrypt;
use App\Models\OpenPlatformApplet;
use App\Models\OpenPlatformPublic;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;

trait CoreTrait
{
    //处理第三方回调事件
    private function event()
    {
        $time_stamp = request('timestamp');
        $nonce = request('nonce');
        $msg_sign = request('msg_signature');
        $encrypt_msg = file_get_contents('php://input');
        $xml_tree = new \DOMDocument();
        $xml_tree->loadXML($encrypt_msg);
        $array_encrypt = $xml_tree->getElementsByTagName('Encrypt');
        $encrypt = $array_encrypt->item(0)->nodeValue;
        $format = "<xml><ToUserName><![CDATA[toUser]]></ToUserName><Encrypt><![CDATA[%s]]></Encrypt></xml>";
        $from_xml = sprintf($format, $encrypt);
        //第三方收到公众平台发送的消息
        $open_platform = config('wechat.open_platform');
        $msg = '';
        $pc = new WXBizMsgCrypt($open_platform['token'], $open_platform['aes_key'], $open_platform['app_id']);
        $errCode = $pc->decryptMsg($msg_sign, $time_stamp, $nonce, $from_xml, $msg);
        // 解密失败
        if ($errCode != 0) {
            $this->error('decryption_error');
        }
        $xml = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml->ComponentVerifyTicket) {
            Redis::set($this->keyName('component_verify_ticket'), $xml->ComponentVerifyTicket);
        }
        return json_decode(json_encode($xml));
    }

    private function keyName($key)
    {
        $main_key = 'openflatform:duanshu:';
        $config = [
            'authorizer_access_token' => $main_key . 'authorizeraccesstoken:',
            'component_access_token'  => $main_key . 'componentaccesstoken',
            'component_verify_ticket' => $main_key . 'componentverifyticket',
        ];
        return $config[$key];
    }

    //获取预授权码
    private function getPreAuthCode()
    {
        $component_access_token = Redis::get($this->keyName('component_access_token'));
        if (!$component_access_token) {
            $component_access_token = $this->getComponentAccessToken();
        }
        $url = config('define.open_platform.wx_applet.api.api_create_preauthcode')
            . '?component_access_token=' . $component_access_token;
        $params = ['component_appid' => config('wechat.open_platform.app_id')];
        $response = $this->curl_trait('POST', $url, $params);
        if (isset($response['pre_auth_code'])) {
            return $response['pre_auth_code'];
        }
        $this->error('no_pre_auth_code');
    }

    //获取第三方平台token
    private function getComponentAccessToken()
    {
        $component_verify_ticket = Redis::get($this->keyName('component_verify_ticket'));
        if (!$component_verify_ticket) {
            $this->error('no_component_verify_ticket');
        }
        $url = config('define.open_platform.wx_applet.api.api_component_token');
        $params = [
            'component_appid'         => config('wechat.open_platform.app_id'),
            'component_appsecret'     => config('wechat.open_platform.secret'),
            'component_verify_ticket' => $component_verify_ticket,
        ];
        $response = $this->curl_trait('POST', $url, $params);
        if ($response['component_access_token']) {
            Redis::setex($this->keyName('component_access_token'),
                $response['expires_in'],
                $response['component_access_token']);
            return $response['component_access_token'];
        }
        $this->error('no_component_access_ticket');
    }

    private function curl_trait($method, $url, $params = '', $headers = '', $body = '', $refresh_token = false)
    {
        try {
            if ($headers || $body) {
                $client = new Client([
                    'headers' => $headers,
                    'body'    => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);
            } else {
                $client = new Client([]);
            }
            $params = $params ? ['json' => $params] : [];
            $response = $client->request($method, $url, $params);
            $response = $response->getBody()->getContents();
            event(new CurlLogsEvent($response,$client,$url));
        } catch (\Exception $e) {
            event(new ErrorHandle($e));
            $this->error('curl_trait_fail');
        }
        if ($response = json_decode($response, 1)) {
            if (isset($response['errmsg']) && ($response['errmsg'] != 'ok')) {
                if ($refresh_token) {
                    return false;
                } else {
                    $errmsg = config('define.open_platform.wx_applet.validation')[$response['errcode']] ?? $response['errmsg'];
                    $this->errorWithText($response['errcode'], $errmsg);
                }
            } else {
                return $response;
            }
        }
    }

    //授权方授权信息
    private function getAuthorizationInfo($auth_code)
    {
        $component_access_token = Redis::get($this->keyName('component_access_token'));
        if (!$component_access_token) {
            $component_access_token = $this->getComponentAccessToken();
        }
        $url = config('define.open_platform.wx_applet.api.api_query_auth')
            . '?component_access_token=' . $component_access_token;
        $params = [
            'component_appid'    => config('wechat.open_platform.app_id'),
            'authorization_code' => $auth_code
        ];
        return $this->curl_trait('POST', $url, $params);
    }

    //获取授权方数据
    private function getAuthorizerInfo($authorizer_appid)
    {
        $component_access_token = Redis::get($this->keyName('component_access_token'));
        if (!$component_access_token) {
            $component_access_token = $this->getComponentAccessToken();
        }
        $url = config('define.open_platform.wx_applet.api.api_get_authorizer_info')
            . '?component_access_token=' . $component_access_token;
        $params = [
            'component_appid'  => config('wechat.open_platform.app_id'),
            'authorizer_appid' => $authorizer_appid
        ];
        return $this->curl_trait('POST', $url, $params);
    }

    //获取授权方token
    private function getAuthorizerAccessToken($appid = '', $shop_id='', $type='')
    {
        if (!$shop_id)
            $shop_id = $this->shop['id'];
        $open_platform = '';
        $authorizationData = [];
        if (!$type)
            $type = $this->type;
        switch ($type) {
            case 'applet':
                $open_platform = OpenPlatformApplet::where('shop_id', $shop_id)->first();
                break;
            case 'public':
                $appid = $appid ?: request('appid');
                $where = ['shop_id' => $shop_id, 'appid' => $appid];
                $open_platform = OpenPlatformPublic::where($where)->first();
                break;
            default:
                break;
        }
        if (!$open_platform) {
            $this->error('no_authorizer');
        }
        $access_token = Redis::get($this->keyName('authorizer_access_token') . $open_platform->appid);
        if (!$access_token) {
            $component_access_token = Redis::get($this->keyName('component_access_token'));
            if (!$component_access_token) {
                $component_access_token = $this->getComponentAccessToken();
            }
            $url = config('define.open_platform.wx_applet.api.api_authorizer_token')
                . '?component_access_token=' . $component_access_token;
            $params = [
                'component_appid'          => config('wechat.open_platform.app_id'),
                'authorizer_appid'         => $open_platform->appid,
                'authorizer_refresh_token' => $open_platform->old_refresh_token
            ];
            $authorizationInfo = $this->curl_trait('POST', $url, $params, '', '', true);
            if (!$authorizationInfo) {
                $params['authorizer_refresh_token'] = $open_platform->refresh_token;
                $authorizationInfo = $this->curl_trait('POST', $url, $params);
            }
            Redis::setex($this->keyName('authorizer_access_token') . $open_platform->appid,
                $authorizationInfo['expires_in'],
                $authorizationInfo['authorizer_access_token']
            );
            $open_platform->access_token = $authorizationInfo['authorizer_access_token'];
            $open_platform->old_refresh_token = $params['authorizer_refresh_token'];
            $open_platform->refresh_token = $authorizationInfo['authorizer_refresh_token'];
            $open_platform->update_time = time();
            $open_platform->save();
        }
        $authorizationData['authorizer_access_token'] = $access_token ?: $authorizationInfo['authorizer_access_token'];
        $authorizationData['open_platform'] = $open_platform;
        return $authorizationData;
    }

    private function handleFuncInfo($func_info)
    {
        $name = '';
        if ($func_info && is_array($func_info)) {
            $func_info_ids = [];
            foreach ($func_info as $vo) {
                $func_info_ids[] = $vo['funcscope_category']['id'];
            }
            $func_info_diff = array_diff([17, 18, 19, 25], $func_info_ids);
            if ($func_info_diff && is_array($func_info_diff)) {
                foreach ($func_info_diff as $vv) {
                    $name .= config('define.open_platform.wx_applet.func_info')[$vv] . ',';
                }
            }
        }
        return $name;
    }
}
