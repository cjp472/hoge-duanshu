<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PintuanGroupEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $param;
    public $order;

    /**
     * PintuanGroupEvent constructor.
     * @param $param
     */
    public function __construct($param,$order)
    {
        $this->param = $param;
        $this->order = $order;

    }

}
