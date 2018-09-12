<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PintuanRefundsRequestEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $fight_group_id;
    /**
     * PintuanRefundsRequestEvent constructor.
     * @param $fight_group_id
     */
    public function __construct($fight_group_id)
    {
        $this->fight_group_id = $fight_group_id;

    }

}
