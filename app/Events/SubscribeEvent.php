<?php
/**
 * 订阅后人数增加
 */

namespace App\Events;


class SubscribeEvent
{
    public function __construct($id,$type, $shop_id, $member_uid, $payment_type)
    {
        $this->id = $id;
        $this->type = $type;
        $this->shop_id = $shop_id;
        $this->member_id = $member_uid;
        $this->payment_type = $payment_type;
    }
}