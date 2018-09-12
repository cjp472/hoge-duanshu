<?php
/**
 * 发送消息事件
 */
namespace App\Events;


class SendMessageEvent
{
    public function __construct($mobile, $slug = '', $param = [])
    {
        $this->mobile = $mobile;
        $this->slug = $slug;
        $this->param = $param;
    }
}