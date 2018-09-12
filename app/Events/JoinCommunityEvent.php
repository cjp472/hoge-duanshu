<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class JoinCommunityEvent
{


    /**
     * 会员加入社群
     * JoinCommunityEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;

    }

}
