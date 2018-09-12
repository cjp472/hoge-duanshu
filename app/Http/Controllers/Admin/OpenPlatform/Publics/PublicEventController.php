<?php

namespace App\Http\Controllers\Admin\OpenPlatform\Publics;

use App\Events\SystemEvent;
use App\Models\AppletRelease;
use App\Models\AppletSubmitAudit;
use Exception;

class PublicEventController extends PublicBaseController
{
    public function handleEvent($event)
    {
        switch ($event->Event) {
            // ---- 菜单事件 ----
            case 'weapp_audit_success':
                return $this->EventWeapp($event);
                break;
            case 'weapp_audit_fail':
                return $this->EventWeapp($event, true);
                break;
            case 'user_enter_tempsession':
                return (new CustomerServiceEventController())->handleEvent($event);
                break;
            // case 'CLICK':
            //     return $this->EventClick($event);
            //     break;
            // case 'VIEW':
            //     return $this->EventView($event);
            //     break;
            // case 'scancode_push':
            //     return $this->EventScancodePush($event);
            //     break;
            // case 'location_select':
            //     return $this->EventLocationSelect($event);
            //     break;
            // // ---- 消息事件 ----
            // case 'subscribe':
            //     return $this->EventSubscribe($event, true);
            //     break;
            // case 'unsubscribe':
            //     return $this->EventSubscribe($event, false);
            //     break;
            // case 'SCAN':
            //     return $this->EventScan($event, false);
            //     break;
            // case 'LOCATION':
            //     return $this->EventLocation($event, false);
            //     break;
            // case 'TEMPLATESENDJOBFINISH':
            //     return $this->TemplateSend($event);
            //     break;
            default:
                return 'success';
                break;
        }
    }

    // 模板消息推送状态接收

    private function EventWeapp($event, $is_fail = false)
    {
        $submit = AppletSubmitAudit::where([
            'primitive_name' => $event->ToUserName,
            'status'         => 2
        ])->first();
        if ($submit) {
            if (!$is_fail) {
                $submit->status = 0;
                $submit->audit_time = $event->SuccTime;
                $params = [
                    'title'   => '审核成功',
                    'content' => '您的小程序已经审核成功,可以发布上线了。'
                ];
//                try{
//                    if($submit->template_id==190){
//                        $shop_id = $submit->shop_id;
//                        $url = config('define.open_platform.wx_applet.api.release')
//                            . '?access_token=' . $this->getAuthorizerAccessToken('', $shop_id, 'applet')['authorizer_access_token'];
//                        $res = $this->curl_trait('POST', $url, new \stdClass());
//                        if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
//                            $where = ['sid' => $submit->id, 'shop_id' => $shop_id];
//                            $release = AppletRelease::where($where)->first();
//                            if ($release) {
//                                $release->release_time = time();
//                                $release->save();
//                                $submit->is_release = 1;
//                                $submit->save();
//                            }
//                        }
//                    }
//                }catch (Exception $e){
//
//                }
            } else {
                $submit->status = 1;
                $submit->reason = $event->Reason;
                $submit->audit_time = $event->FailTime;
                $params = [
                    'title'   => '审核失败',
                    'content' => $event->Reason
                ];
            }
            $submit->callback = $event ? serialize($event) : [];
            $submit->save();
            $params['shop_id'] = $submit->shop_id;
            $this->systemEvent($params);
        }
        return 'success';
    }

    // 定位事件

    private function systemEvent($params)
    {
        event(new SystemEvent($params['shop_id'], $params['title'], $params['content'], 0,
            -1, '系统管理员'));
    }

    // 关注／取关事件

    private function EventClick($event)
    {
        // 事件定义的Key值
        $event_key = $event->EventKey;
        return '';
    }

    // 关注过的用户扫描二维码事件

    private function EventView($event)
    {
        $redirect_url = $event->EventKey;
        $menu_id = $event->MenuID;
        return '';
    }

    // 小程序审核回调

    private function EventScancodePush($event)
    {
        $event_key = $event->EventKeyEventKey;
        return '';
    }

    // 菜单点击事件

    private function EventLocationSelect($event)
    {
        $send_location_info = $event->SendLocationInfo;
        return '';
    }

    // 点击菜单跳转链接时的事件推送

    private function EventSubscribe($event, $is_sub = false)
    {
        if ($is_sub) {
            if (isset($event->Ticket)) {
                if ($event->Ticket) {
                    $type = 'scancode';
                } else {
                    $type = 'subscribe';
                }
            }
        } else {
            $type = 'unsubscribe';
        }
        return '';
    }

    // scancode_push：扫码推事件的事件推送

    private function EventScan($event)
    {
        $event_key = $event->EventKey;
        $ticket = $event->Ticket;
        return '';
    }

    private function EventLocation($event)
    {
        $latitude = $event->Latitude;
        $longitude = $event->Longitude;
        $precision = $event->Precision;
        return '';
    }

    private function TemplateSend($event)
    {
        $status = $event->Status;
        return '';
    }
}
