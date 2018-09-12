<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PintuanPaymentEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $fight_group_id;
    public $order;


    /**
     * PintuanPaymentEvent constructor.
     * @param $fight_group_id
     * @param $order
     */
    public function __construct($fight_group_id,$order)
    {
        $this->fight_group_id = $fight_group_id;
        $this->order = $order;
    }

}
