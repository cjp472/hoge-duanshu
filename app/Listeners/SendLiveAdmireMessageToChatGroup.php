<?php

namespace App\Listeners;

use Exception;
use App\Events\AdmireEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLiveAdmireMessageToChatGroup
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  AdmireEvent  $event
     * @return void
     */
    public function handle(AdmireEvent $event)
    {
        $admireOrder = $event->admireOrder;

        if($admireOrder->content_type != 'live'){
            return true;
        }

        $url = config('define.python_duanshu.api.send_chat_group_admire_msg');
        $url = sprintf($url, $admireOrder->content_id);
        $appId = config('define.inner_config.sign.key');
        $appSecret = config('define.inner_config.sign.secret');
        $payload = ['admire_order' => $admireOrder->id];
        $client = hg_verify_signature($payload, '', $appId, $appSecret, $admireOrder->shop_id);
        try {

            $response = $client->post($url, ['http_errors' => true]);
            $return  = $response->getBody()->getContents();
            event(new CurlLogsEvent($return, $client, $url));
            $r = json_decode($return);
            if(array_key_exists('error',$r)){
                $e = new Exception("发送赞赏消息失败 url $url \npayload $payload \nreturn $return");
                throw $e;
            }
        } catch (\Exception $exception) {
            event(new ErrorHandle($exception));
            return false;
        }
        return true;
    }
}
