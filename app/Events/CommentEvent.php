<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CommentEvent
{
    /**
     * 创建一个事件实例。
     *
     * @param  Order  $order
     * @return void
     */
    public function __construct($content_id,$content_type,$shop_id)
    {
        $this->id = $content_id;
        $this->type = $content_type;
        $this->shop_id = $shop_id;
    }
}
