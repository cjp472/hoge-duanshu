<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AdminLogsEvent
{
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($response,$title = '')
    {
        $this->param = [
            'ip'             => hg_getip(),
            'type'           => Route::currentRouteName(),
            'title'          => $this->setLogTitle($title ?: ''),
            'route'          => app('request')->fullUrl(),
            'input_data'     => app('request')->all() ? json_encode(app('request')->all()) : '',
            'user_agent'     => app('request')->server->get('HTTP_USER_AGENT'),
            'output_data'    => $response->getContent(),
            'operate_time'   => time(),
            'user_id'        => Auth::id() ? : '',
            'user_name'      => isset(Auth::user()->name) ? Auth::user()->name : '',
        ];
    }

    private function setLogTitle($title = '')
    {
        $prefix = trans('log.'.Route::currentRouteName());
        return $title ? '【'.$prefix.'】'.$title :  '【'.$prefix.'】';
    }

}
