<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/5/17
 * Time: 10:18
 */

namespace App\Http\Middleware\App;

use App\Models\ShopApp;
use Closure;

class CheckSignature
{
    public function handle($request, Closure $next)
    {
        if(!$request->header('X-API-SIGNATURE') || !$request->header('X-API-KEY')){
            return response([
                'error_code'     => 'no-signature-param',
                'error_message'   => trans('validation.no-signature'),
            ]);
        }
        if($this->check_signature($request)){
            return $next($request);
        }
        return response([
            'error_code'     => 'error-signature',
            'error_message'   => trans('validation.error-signature'),
        ]);
    }

    private function check_signature($request)
    {
        $timestamp = $request->header('X-API-TIMESTAMP');
        $sign = $request->header('X-API-SIGNATURE');
        $appKey = $request->header('X-API-KEY');
        $version = $request->header('X-API-VERSION');
        $shop = ShopApp::where(['appkey'=>$appKey])->first(['appsecret','shop_id']);
        $signature = '';
        if($shop){
            $timestamp = $timestamp ?: time();
            $string = $appKey.'&'.$shop->appsecret.'&'.$version.'&'.$timestamp;
            $signature = sha1($string);
            $request->merge(['shop_id'=>$shop->shop_id]);
        }
        return $signature == $sign ? true : false;

    }

}