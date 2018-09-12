<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CreateWechatArticleEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $media_id;
    public $shop_id;
    public $user;

    /**
     * CreateWechatArticleEvent constructor.
     * @param $media_id
     * @param $shop_id
     * @param $user
     */
    public function __construct($media_id,$shop_id,$user)
    {
        $this->media_id = $media_id;
        $this->shop_id = $shop_id;
        $this->user = $user;

    }


}
