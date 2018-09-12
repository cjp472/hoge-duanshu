<?php

namespace App\Events\AppEvent;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AppTypeDeleteEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $shop_id;
    public $id;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($shop_id,$id)
    {
        $this->shop_id = $shop_id;
        $this->id = $id;
    }
}
