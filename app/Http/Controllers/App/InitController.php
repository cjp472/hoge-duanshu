<?php
/**
 * app端的基类
 */
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Psy\Util\Json;

class InitController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $member = $request->header('x-member');
            $member && $sign = json_decode(urldecode($member),1);
            $this->member = $member ? [
                'id'        => $sign['id'],
                'nick_name' => $sign['nick_name'],
                'openid'    => $sign['openid'],
                'source'    => $sign['source'],
                'avatar'    => $sign['avatar'],
            ] :  [
                'id'        => 'test12345',
                'nick_name' => 'test-member',
                'openid'    => 'test-666666666',
                'source'    => 'wechat',
                'avatar'    => 'http://wx.qlogo.cn/mmopen/PiajxSqBRaEJQ5LJuzIVYAsNdcIpZnIIhmNLH2PhBPhC9rzg8K0P92oQIy9f5ERwibtJTrxwSB791tDGwKSwXufJHQxf02ibicVomj81cGXpa1w/0',
            ] ;
            $this->shop = [
                'id' => $request->shop_id
            ];
            return $next($request);
        });
    }

    protected function validateAppParam($validator,$attribute = [])
    {
        try{
            $this->validateWithAttribute($validator,$attribute);
        }catch (ValidationException $validationException){
            $validation = $validationException->validator->errors()->getMessages();
            if($validation && is_array($validation)){
                foreach ($validation as $key=>$item){
                    $response = response(json_encode([
                        'error_code' => 'error_' . $key,
                        'error_message' => $item[0],
                    ]), 422);
                    throw new HttpResponseException($response);
                }
            }
        }
    }

    protected function signature($response)
    {
        $timestamp = time();
        $randomStr = str_random(12);
        $data = $response ?: [];
        $sign = [
            'timestamp' => $timestamp,
            'randomstr' => $randomStr,
            'secret' => MEMBER_SECRET,
            'expire' => $timestamp + MEMBER_EXPIRE,
        ];
        $ret = array_merge($data,$sign);
        ksort($ret);
        $signurl = '';
        foreach($ret as $k=>$v)
        {
            $signurl .= $k.'='.$v.'&';
        }
        $signurl = trim($signurl,'&');
        $signature = sha1($signurl);
        $ret['signature'] = $signature;
        return $ret;
    }
}