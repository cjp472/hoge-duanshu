<?php
/**
 * 预订单后同步接口
 */

namespace App\Events;

use App\Models\Order;

class OrderMakeEvent
{
    public function __construct(Order $order,$content,$request)
    {
        $this->order = $order;
        $this->content = $content;
        $this->request = $request;
    }
}