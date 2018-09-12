<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderStatusEvent
{


    /**
     * OrderStatusEvent constructor.
     * @param $request
     * @param $shop_id
     */
    public function __construct($request,$shop_id)
    {
        $this->param = [
            'order_no'  => $request['out_trade_no'],
            'status'    => 'success',
            'pay_channel'   => 'weapp',
            'receipt_amount'    => $request['total_fee']
        ];
        $this->shop_id = $shop_id;
    }

}
