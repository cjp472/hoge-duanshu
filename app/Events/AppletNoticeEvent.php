<?php
/**
 * Created by PhpStorm.
 * User: a123456
 * Date: 2017/6/1
 * Time: 上午10:00
 */

namespace App\Events;


class AppletNoticeEvent
{
    public function __construct($shop_id,$content,$user_id,$user_name)
    {
        $this->params = [
            'shop_id'   => $shop_id,
            'content'   => $content,
            'user_id'   => $user_id,
            'user_name' => $user_name,
            'send_time' => time()
        ];

    }

}