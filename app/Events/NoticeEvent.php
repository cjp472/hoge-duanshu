<?php
/**
 * 发送消息事件
 */
namespace App\Events;


use App\Models\Notice;

class NoticeEvent
{
    public function __construct($type,$content,$shop_id,$user_id = -1,$user_name = '系统消息',$link_info=[],$sender_name='系统消息')
    {
        $this->type = $type;
        $this->content = $content;
        $this->shop_id = $shop_id;

        $this->user_id = $user_id;
        $this->user_name = $user_name;
        $this->link_info = $link_info;
        $this->sender_name = $sender_name;
    }
}