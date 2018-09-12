<?php
/**
 * 预订单后同步接口
 */

namespace App\Events;


class AdmireOrderEvent
{
    public function __construct($order)
    {
        $this->order = $order;
    }
}