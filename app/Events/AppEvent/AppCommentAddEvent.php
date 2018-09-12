<?php

namespace App\Events\AppEvent;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AppCommentAddEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;
    public $shop_id;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($shop_id,$ori_id,$content_id,$content,$member_id,$fid)
    {
        $this->shop_id = $shop_id;
        $info = [
            'ori_content_id'     => $content_id,
            'ori_id'             => $ori_id,
            'uid'                => $member_id,
            'create_time'        => hg_format_date(),
            'comment'            => $content,
            'img'                => [],
            'star'               => 0,
            'is_anonymous'       => false,
            'like'               => 0,
        ];
        $fid && $info['reply_comment_id'] = $fid;
        $this->data = [$info];
    }

}
