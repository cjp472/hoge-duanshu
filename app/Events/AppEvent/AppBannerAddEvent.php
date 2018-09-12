<?php

namespace App\Events\AppEvent;

use App\Models\AppContent;
use App\Models\ShopApp;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AppBannerAddEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $shop_id;
    public $data;
    public $shop_app;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($shop_id,$id,$title,$indexpic,$link)
    {
        $this->shop_app = ShopApp::where('shop_id',$shop_id)->first();
        $event = '';
        if($this->shop_app) {
            if ($link['type'] == 'outLink') {
                $event = $link['name'];
            } elseif ($link['type'] != 'none') {
                $appContentId = AppContent::where(['content_id' => $link['id'], 'content_type' => $link['type']])->value('app_content_id');
                $model = unserialize($this->shop_app->model_slug)[$link['type']];
                $event = 'dingdone://detail?content_id=' . $appContentId . '&model=' . $model;
            }
        }
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
            'event'  => $event
        ];
    }
}
