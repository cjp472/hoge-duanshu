<?php
/**
 * Created by PhpStorm.
 * User: Janice
 * Date: 2018/5/18
 * Time: 09:23
 */

namespace App\Listeners;


use App\Events\CurlLogsEvent;
use App\Events\SendMessageEvent;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class SendMessage
{
    public function handle(SendMessageEvent $event)
    {
        if(!is_array($event->mobile)){
            $mobile = explode(',',$event->mobile);
        }else{
            $mobile = $event->mobile;
        }
        $param = [
            "template" => $event->slug,
            "kwargs" => $event->param,
            "target" => $mobile,
        ];
        $this->sendMobileCodeByStore($param,config('define.service_store.api.sms'));
    }

    /**
     * 使用服务商城发送短信验证码
     */
    protected function sendMobileCodeByStore($param,$url)
    {
        $jsonParam = json_encode($param);
        $key = config('define.service_store.app_id');
        $timestamp = time();
        $signParam = 'access_key='.$key.'&access_secret='.config('define.service_store.app_secret').'&timestamp='.$timestamp.'&raw_data='.$jsonParam;
        $signature = strtoupper(md5($signParam));
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-timestamp'   => $timestamp,
                'x-api-key'         => $key,
                'x-api-signature'   => $signature,
                'authorization' => 'duanshu-sms-sender'
            ],
            'body'  => $jsonParam,
        ]);
        $response = $client->request('post',$url);
        $response = json_decode($response->getBody()->getContents());
        event(new CurlLogsEvent(json_encode($response),$client,$url));
        if($response && isset($response->error)){
        }
        if(is_array($param['target'])){
            foreach ($param['target'] as $mobile){
                Cache::put('mobile:'.$mobile,1,1);    //记录已发送状态,过期时间1分钟
            }
        }
        return $response;
    }
}