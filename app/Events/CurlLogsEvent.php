<?php

namespace App\Events;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Auth;


class CurlLogsEvent
{

    /**
     * CurlLogsEvent constructor.
     * @param $response
     * @param Client $client
     * @param $route
     */
    public function __construct($response,Client $client,$route)
    {
        $this->param = [
            'route' => $route,
            'user_id'   => Auth::id() ? : '',
            'user_name' => isset(Auth::user()->name) ? Auth::user()->name : '',
            'input_data'    => json_encode(['param' => $client->getConfig('body'),'headers' => $client->getConfig('headers'),'local_route' => app('request')->fullUrl()]) ? : '',
            'output_data'   => is_object($response) ? $response->getBody()->getContents() : $response,
            'time'  => time(),
            'ip'    => hg_getip(),
            'user_agent' => app('request')->server->get('HTTP_USER_AGENT'),
        ];

    }

}
