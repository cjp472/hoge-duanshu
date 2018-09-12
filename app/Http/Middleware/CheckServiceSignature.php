<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/5/17
 * Time: 10:18
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Route;

class CheckServiceSignature
{
    public function handle($request, Closure $next)
    {
        if($this->check_signature($request)){
            return $next($request);
        }
        return response([
            'error'     => 'error-signature',
            'message'   => trans('validation.error-signature'),
        ]);
    }

    private function check_signature($request)
    {
        $timestamp = $request->header('x-api-timestamp');
        $sign = $request->header('x-api-signature');
        $param = [
            'access_key'   => $request->header('x-api-key'),
            'access_secret'=> in_array(Route::currentRouteName(),['fightGroupCallback','fightGroupRefundRetry','fightGroupRetry']) ? config('define.inner_config.sign.secret') : config('define.service_store.sign.secret'),
            'timestamp'    => $timestamp,
        ];
        $callback = [
            'verifyCallback',
            'withdrawAccountCallback',
            'withdrawNotify',
            'memberWithdrawCallback',
            'rateConfig',
        ];
        $currentRoute = Route::currentRouteName();
        if(in_array($currentRoute,$callback) ){
            $param['raw_data']  = $request->getContent();
        }
        $string = '';
        foreach ($param as $k=>$v){
            $string .= $k.'='.$v.'&';
        }
        $string = trim($string,'&');
        $signature = strtoupper(md5($string));
        return $signature == $sign ? true : false;
    }

}