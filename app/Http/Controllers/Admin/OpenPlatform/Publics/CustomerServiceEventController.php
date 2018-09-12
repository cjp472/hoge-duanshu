<?php

namespace App\Http\Controllers\Admin\OpenPlatform\Publics;

use App\Events\SystemEvent;
use App\Http\Controllers\Admin\OpenPlatform\SampleCode\WXBizMsgCrypt;
use App\Models\AppletSubmitAudit;
use App\Models\OpenPlatformApplet;
use App\Models\Shop;

/**
 * 客服消息处理
 * Class CustomerServiceEventController
 * @package App\Http\Controllers\Admin\OpenPlatform\Publics
 */
class CustomerServiceEventController extends PublicBaseController
{
    public function handleEvent($event)
    {
        //用户打开临时会话 例如客服
        //文本消息
        //图片消息
        if($event->MsgType == 'event' && $event->Event=='user_enter_tempsession'
            || $event->MsgType == 'text' || $event->MsgType == 'image'){
            return $this->transferCustomerService($event);
        }
        return 'success';
    }

    private function transferCustomerService($event){
        if($event && isset($event->ToUserName)){
            //小程序原始id
            $primitive_name = $event->ToUserName;
            $open_platform_applet = OpenPlatformApplet::where(['primitive_name'=>$primitive_name])->select(['shop_id', 'appid', 'primitive_name'])->first();
            if ($open_platform_applet) {
                $shop = Shop::where(['hashid'=>$open_platform_applet->shop_id])->first();
                // 店铺是高级版本且开启了小程序客服
                if($shop && ($shop->version == VERSION_ADVANCED || $shop->version == VERSION_STANDARD) && $shop->enable_customer_service == 1){
                    //将消息转发到客服
                    $xmlTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[transfer_customer_service]]></MsgType>
                        </xml>";
                    $result = sprintf($xmlTpl, $event->FromUserName, $event->ToUserName, time());
                    $open_platform = config('wechat.open_platform');
                    $pc = new WXBizMsgCrypt($open_platform['token'], $open_platform['aes_key'], $open_platform['app_id']);
                    $msg = '';
                    $pc->encryptMsg($result, time(), time(),$msg);
                    return $msg;
                }
            }
        }
        return 'success';
    }
}
