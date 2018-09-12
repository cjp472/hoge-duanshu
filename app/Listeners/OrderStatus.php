<?php

namespace App\Listeners;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\OrderStatusEvent;
use GuzzleHttp\Client;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderStatus
{

    /**
     * Handle the event.
     *
     * @param  OrderStatusEvent  $event
     * @return void
     */
    public function handle(OrderStatusEvent $event)
    {
        $appId = config('define.order_center.app_id');
        $appSecret = config('define.order_center.app_secret');
        $timesTamp = time();
        $client = hg_verify_signature($event->param,$timesTamp,$appId,$appSecret,$event->shop_id);
        $url = str_replace('{order_no}', $event->param['order_no'], config('define.order_center.api.order_undeliver'));
        try {
            $response = $client->request('PUT', $url, ['query' => $event->param]);
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
            return false;
        }
        event(new CurlLogsEvent($response,$client,$url));
    }
}
