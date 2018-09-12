<?php
/**
 * Created by PhpStorm.
 * User: a123456
 * Date: 2017/6/1
 * Time: 上午10:00
 */

namespace App\Events;


class SystemEvent
{
    public function __construct($shop_id,$title,$content,$type,$user_id,$user_name,$top=0)
    {
        $this->params = [
            'shop_id'   => $shop_id,
            'title'     => $title,
            'content'   => $content,
            'send_type' => $type,
            'user_id'   => $user_id,
            'user_name' => $user_name,
            'send_time' => time(),
            'top' => $top,
        ];

    }



}