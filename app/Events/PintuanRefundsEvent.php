<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PintuanRefundsEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $param;

    /**
     * PintuanRefundsEvent constructor.
     * @param $params
     */
    public function __construct($params)
    {
        $this->param = $params;
    }

}
