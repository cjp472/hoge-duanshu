<?php
namespace App\Events;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class OperationEvent
{
    public function __construct($response,$title = '')
    {
        $this->param = [
            'title' => $this->setLogTitle($title ?: ''),
            'type'  => Route::currentRouteName(),
            'route' => app('request')->fullUrl(),
            'user_id'   => Auth::id() ? : '',
            'user_name' => isset(Auth::user()->name) ? Auth::user()->name : '',
            'input_data'    => app('request')->all() ? json_encode(app('request')->all()) : '',
            'output_data'   => $response->getContent(),
            'time'  => time(),
            'ip'    => hg_getip(),
            'user_agent' => app('request')->server->get('HTTP_USER_AGENT') ? : '',
        ];
    }

    private function setLogTitle($title = '')
    {
        $prefix = trans('log.'.Route::currentRouteName());
        return $title ? '【'.$prefix.'】'.$title :  '【'.$prefix.'】';
    }
}