<?php
/**
 * 支付后会员消费总额增加
 */

namespace App\Events;


class SalesTotalEvent
{
    public function __construct($order)
    {
        $this->order = $order;
    }
}