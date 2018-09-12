<?php
namespace App\Events;

use Exception;
use Illuminate\Support\Facades\Auth;

class ErrorHandle
{
    public function __construct(Exception $e,$type = 'duanshu')
    {
        $class = explode("\\",get_class($e));
        $request = app('request');
        $this->param = [
            'route'         => $request->fullUrl(),
            'input_data'    => json_encode([
                'query' => $request->query->all(),
                'request'   => $request->request->all(),
                'attributes'    => $request->attributes->all(),
                'cookies'   => $request->cookies->all(),
                'files' => $request->files->all(),
                'server'    => [
                    'agent' => $request->server->get('HTTP_USER_AGENT'),
                    'server_addr'   => $request->server->get('SERVER_ADDR'),
                    'remote_addr'   => $request->server->get('REMOTE_ADDR'),
                    'request_method'    => $request->server->get('REQUEST_METHOD'),
                ],
            ]),
            'user_id'       => 0,
            'user_name'     => '',
            'type'          => $type,
            'error'         => $e->__toString(),
            'time'          => time(),
            'ip'            => hg_getip(),
            'classtype'     => end($class),
        ];

        $this->error = [
            'code'  => $e->getCode(),
            'line'  => $e->getLine(),
            'file'  => $e->getFile(),
            'message'   => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'class' => end($class),
        ];

    }
}