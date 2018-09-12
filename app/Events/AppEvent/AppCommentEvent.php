<?php

namespace App\Events\AppEvent;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AppCommentEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $comment_id;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($comment_id)
    {
        $this->comment_id = $comment_id;
    }

}
