<?php
namespace App\Events;

use Illuminate\Support\Facades\Route;

class H5LogsEvent
{
    public function __construct($response,$title = '',$member_id,$member_name)
    {
        $this->param = [
            'title' => $this->setLogTitle($title ?: ''),
            'type'  => Route::currentRouteName(),
            'route' => app('request')->fullUrl(),
            'user_id'   => $member_id ? : '',
            'user_name' => $member_name ? : '',
            'input_data'    => json_encode(['param' => app('request')->all(),'header' => app('request')->header()]),
            'output_data'   => $response->getContent(),
            'time'  => time(),
            'ip'    => hg_getip(),
            'user_agent' => app('request')->server->get('HTTP_USER_AGENT'),
        ];
    }

    private function setLogTitle($title = '')
    {
        $prefix = trans('log.'.Route::currentRouteName());
        return $title ? '【'.$prefix.'】'.$title :  '【'.$prefix.'】';
    }
}