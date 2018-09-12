<?php
namespace App\Http\Controllers\Admin\OpenPlatform;

use App\Http\Controllers\Admin\BaseController;
use App\Http\Controllers\Admin\OpenPlatform\SampleCode\WXBizMsgCrypt;
use GuzzleHttp\Client;

class FullWebController extends BaseController
{
    use CoreTrait;

    public function fullWebPublishUtil()
    {
        $event = $this->event();
        switch ($event->MsgType) {
            case 'event':
                return $this->backText($event, 'LOCATIONfrom_callback');
                break;
            case 'text':
                return $this->text($event);
                break;
            default:
                return 'success';
                break;
        }
    }

    private function backText($event, $content)
    {
        $xmlTpl = "<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[%s]]></Content></xml>";
        $result = sprintf($xmlTpl, $event->FromUserName, $event->ToUserName, time(), $content);

        $config = config('wechat.open_platform');
        $pc = new WXBizMsgCrypt($config['token'], $config['aes_key'], $config['app_id']);
        $encryptMsg = '';
        $pc->encryptMsg($result, request('timestamp'), request('nonce'), $encryptMsg);
        return $encryptMsg;
    }

    private function text($event)
    {
        // 根据文本消息的内容进行相应的响应
        if (false !== strpos($event->Content, 'TESTCOMPONENT_MSG_TYPE_TEXT')) {
            $content = 'TESTCOMPONENT_MSG_TYPE_TEXT_callback';
            return $this->backText($event, $content);
        } // 客服消息接口发送消息回复粉丝
        elseif (false !== strpos($event->Content, 'QUERY_AUTH_CODE')) {
            $content = explode(':', $event->Content);
            $authorizationCode = end($content);
            $msg = [
                'touser'  => $event->FromUserName,
                'msgtype' => 'text',
                'text'    => [
                    'content' => $authorizationCode . '_from_api',
                ]
            ];
            $this->sendMessage($msg, $authorizationCode);
            return '';
        }
    }

    private function sendMessage($msg, $code)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=' . $this->getAcToken($code);
        (new Client)->request('POST', $url, ['json' => $msg])->getBody()->getContents();
    }

    private function getAcToken($code)
    {
        $res = $this->getAuthorizationInfo($code);
        return $res['authorization_info']['authorizer_access_token'];
    }
}
