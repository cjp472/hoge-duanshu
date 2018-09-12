<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PintuanRefundsPassEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $refund_id;
    public $shop_id;

    /**
     * PintuanRefundsPassEvent constructor.
     * @param $refund_order
     */
    public function __construct($refund_order)
    {

        $this->refund_id = $refund_order->order_center_refund_id;
        $this->shop_id = $refund_order->shop_id;

    }

}
