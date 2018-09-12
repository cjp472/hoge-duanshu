<?php
namespace App\Http\Controllers\H5\Xiuzan;

use App\Events\CurlLogsEvent;
use App\Http\Controllers\H5\BaseController;
use GuzzleHttp\Client;

class FeedbackController extends BaseController
{
    public function Lists()
    {
        $jsonParam = json_encode([
            'count' => request('count') ?: 10,
            'mark'  => 'feedback',
            'state' => 1,
            'source' => 'duanshu',
            'user_id' => $this->shop['id'],
        ]);
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'  => $jsonParam,
        ]);
        $url = config('define.xiuzan.api.lists');
        $response = $client->request('get',$url);
        $response = json_decode($response->getBody()->getContents());
        event(new CurlLogsEvent(json_encode($response),$client,$url));
        if($response && isset($response->data)){
            $ret['data'] = $response->data;
            $ret['page'] = [
                'total' => $response->pageinfo->total,
                'current_page' => $response->pageinfo->current_page,
                'last_page' => $response->pageinfo->last_page,
            ];
        }else{
            $ret['data'] = [];
            $ret['page'] = [
                'total' => 0,
                'current_page' => 1,
                'last_page' => 0,
            ];
        }
        return $this->output($ret);
    }
}