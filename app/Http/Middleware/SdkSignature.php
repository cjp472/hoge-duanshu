<?php
namespace App\Http\Middleware;

use Closure;
use App\Models\Sdk;

class SdkSignature
{
    public function handle($request, Closure $next)
    {
        $sign = $request->header('signature');
        if(empty($sign)){
            return response([
                'error'   => 'error-signature',
                'message' => trans('validation.error-signature'),
            ]);
        }
        $input = $request->all();
        $obj = Sdk::where('app_id',$input['app_id'])->first();
        if(empty($obj)){
            return response([
                'error'   => 'data-not-fond',
                'message' => trans('validation.data-not-fond'),
            ]);
        }
        $appId = $obj->app_id;
        $appSecret = $obj->app_secret;
        $timestamp = $request->header('timestamp');
        $signature = sha1($appId.'&'.$appSecret.'&'.$timestamp);
        if($sign != $signature){
            return response([
                'error'   => 'error-signature',
                'message' => trans('validation.error-signature'),
            ]);
        }
        return $next($request);
    }
}