<?php

namespace App\Listeners;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\PintuanRefundsPassEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class PintuanRefundsPass
{

    /**
     * Handle the event.
     *
     * @param  PintuanRefundsPassEvent  $event
     * @return void
     */
    public function handle(PintuanRefundsPassEvent $event)
    {
        $client = hg_verify_signature('','','','',$event->shop_id);
        $url = str_replace('{refund_id}', $event->refund_id, config('define.order_center.api.m_order_refunds_success'));
        try{
            $res = $client->request('PUT',$url);
            $refunds_return = $res->getBody()->getContents();
            event(new CurlLogsEvent($refunds_return,$client,$url));
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
            return false;
        }
    }
}
