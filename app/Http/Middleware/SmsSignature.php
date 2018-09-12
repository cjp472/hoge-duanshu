<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/5/10
 * Time: 16:01
 */

namespace App\Http\Middleware;

use Closure;

class SmsSignature
{
    public function handle($request,Closure $next){

        if($this->check_sms_signature($request)){
            return $next($request);
        }
        return response([
            'error'     => 'error-signature',
            'message'   => trans('validation.error-signature'),
        ]);
    }

    private function check_sms_signature($request)
    {
        $sign = $request->header('x-api-signature');
        $param = [
            'timestamp' => $request->header('x-api-timestamp'),
            'access_key'=> $request->header('x-api-key'),
            'access_secret'=> config('sms.sign_param.secret'),
        ];
        $signs = hg_hash_sha256($param);
        return $sign == $signs ? true : false;
    }

}