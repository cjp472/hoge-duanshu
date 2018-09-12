<?php
/**
 * 短信发送
 */

namespace App\Http\Controllers\Sms\DH3tong;

use App\Events\CurlLogsEvent;
use App\Http\Controllers\Sms\BaseController;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class SmsController extends BaseController
{

    /**
     * 单条短信
     */
    public function submit(){
        $this->validateSubmitParam();
        $param = $this->organizeSubmitParam();
        $response = $this->submitSms($param);
        return $this->output(['success'=>1]);
    }

    /**
     * 验证短信发送参数
     */
    private function validateSubmitParam(){
        $this->validateWithAttribute([
            'content'    => 'required|max:350',
            'mobile'     => 'required|regex:/^1[3,5,7,8]\d{9}(,1[3,5,7,8]\d{9})*$/'
        ],[
            'content'    => '信息内容',
            'mobile'     => '手机号码'
        ]);
    }

    /**
     * 组织发送参数
     */
    private function organizeSubmitParam(){
        $param = [
            'account'   => config('sms.account'),
            'password'  => md5(config('sms.password')),
            'sign'      => config('sms.sign'),
            'phones'    => request('mobile'),
            'content'   => trim(request('content')),
        ];
        return $param;
    }

    /**
     * 发送消息
     * @param $param
     * @return mixed|string
     */
    private function submitSms($param){
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'  => json_encode($param)
        ]);
        $response = $client->request('POST',config('sms.api.submit'));
        $response = json_decode($response->getBody()->getContents());
        event(new CurlLogsEvent(json_encode($response),$client,config('sms.api.submit')));
        if($response && $response->result){
            $this->errorWithText($response->result,$response->desc);
        }
        return $response;
    }

    /**
     * 多条短信
     */
    public function bitchSubmit(){

    }


    /**
     * 发送手机验证码
     */
    public function mobile(){
        $this->validateWithAttribute(['mobile'=> 'required|regex:/^1[3,5,7,8]\d{9}(,1[3,5,7,8]\d{9})*$/']);
        $verify_code = $this->generateMobileCode();
        $param = [
            'mobile'    => request('mobile'),
            'content'   => preg_replace('/{code}/',$verify_code,config('sms.template.mobile_code')),
        ];
        $this->sendMobileCode($param,config('sms.api.mobile_send'));
        return $this->output(['success' => 1]);
    }


}