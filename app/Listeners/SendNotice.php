<?php
/**
 * 发送消息
 */
namespace App\Listeners;

use App\Events\NoticeEvent;
use App\Models\Notice;

class SendNotice
{
    public function handle(NoticeEvent $event)
    {
        $notice = new Notice();
        if($event->type == 1){
            $notice->recipients = -1;
            $notice->recipients_name = '所有人';
        }else{
            $notice->recipients = $event->user_id;
            $notice->recipients_name = $event->user_name;
        }
        $notice->type = $event->type ? 1 : 0;
        $notice->content = $event->content;
        $notice->sender = -1;
        $notice->sender_name = $event->sender_name ? : '系统消息';
        $notice->send_time = time();
        $notice->status = 1;
        $notice->shop_id = $event->shop_id;
        $notice->link_info = $event->link_info ? serialize($event->link_info) : '';
        $notice->save();
    }
}