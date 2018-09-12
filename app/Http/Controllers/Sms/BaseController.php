<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/7/19
 * Time: 17:11
 */

namespace App\Http\Controllers\Sms;


use App\Events\CurlLogsEvent;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class BaseController extends Controller
{
    /**
     * 生成手机验证码
     * @return int
     */
    protected function generateMobileCode(){
        $status = Cache::get('mobile:'.request('mobile'));
        if($status){
            $this->error('mobile_code_already_send');
        }
        $code = rand(1000,9999);
        Cache::put('mobile:code:'.request('mobile'),$code,MOBILE_CODE_EXPIRE / 60);
        return $code;
    }

    /**
     * 发送验证码
     * @param $param
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    protected function sendMobileCode($param,$url){
        $timestamp = time();
        $signature = hg_hash_sha256([
            'timestamp' => $timestamp,
            'access_key'=> config('sms.sign_param.key'),
            'access_secret'=> config('sms.sign_param.secret'),
        ]);

        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-timestamp'   => $timestamp,
                'x-api-key'         => config('sms.sign_param.key'),
                'x-api-signature'   => $signature,
            ],
            'body'  => json_encode($param),
        ]);
        $response = $client->request('POST',$url);
        $response = json_decode($response->getBody()->getContents());
        event(new CurlLogsEvent(json_encode($response),$client,$url));
        if($response && isset($response->error)){
            $this->errorWithText($response->error,$response->message);
        }
        Cache::put('mobile:'.request('mobile'),1,1);    //记录已发送状态,过期时间1分钟
        return $response;
    }

    /**
     * 使用服务商城发送短信验证码
     */
    protected function sendMobileCodeByStore($param, $url)
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
        if($response->error_code != 0){
            $this->error('send-sms-error');
        }
        event(new CurlLogsEvent(json_encode($response),$client,$url));
        return $response;
    }

}