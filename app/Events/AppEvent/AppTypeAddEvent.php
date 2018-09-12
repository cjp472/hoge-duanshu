<?php

namespace App\Events\AppEvent;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AppTypeAddEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $shop_id;
    public $data;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($shop_id,$id,$title,$indexpic)
    {
        $indexpic = hg_explore_image_link($indexpic);
        $this->shop_id = $shop_id;
        $this->data = [
            'ori_id'   => $id,
            'title'    => $title,
            'indexpic' => [
                'source'     => 'duanshu',
                'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                'filename'   => '',
                'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                'filesize'   => 0,//hg_get_file_size($indexpic['host'].$indexpic['file']),
                'dir'        => '',
            ],
            'is_show'  => true
        ];
    }
}
