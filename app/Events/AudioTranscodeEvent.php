<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AudioTranscodeEvent
{
    /**
     * AudioTranscodeEvent constructor.
     * @param $file_path
     * @param $file_name
     * @param $target_file_name
     * @param $dstPath
     */
    public function __construct($file_path,$file_name,$target_file_name,$dstPath)
    {
        $this->file = $file_path.$file_name;
        $this->target_file = $file_path.$target_file_name;
        $this->file_name = $target_file_name;
        $this->dstPath = $dstPath;
    }

}
