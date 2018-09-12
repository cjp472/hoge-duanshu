<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/7/5
 * Time: 09:11
 */

namespace App\Http\Middleware\H5;

use Closure;
use Illuminate\Support\Facades\Cache;

class WechatApplet
{
    public function handle($request, Closure $next)
    {
        $rawData = trim($request['rawData']);
        $signature = trim($request['signature']);
        $sessionToken = trim($request['sessionToken']);
        $session_key = Cache::get($sessionToken);
        if (!$session_key) {
            return response([
                'error' => 'NO_SESSION_KEY',
                'message' => trans('validation.NO_SESSION_KEY'),
            ]);
        }
        if (!$signature){
            return response([
                'error' => 'NO_SIGNATURE',
                'message' => trans('validation.required', ['attribute' => '签名']),
            ]);
        }
        $new_signature = sha1($rawData.$session_key);
        if ($signature != $new_signature)
        {
            return response([
                'error' => 'NO_MATCH_SIGNATURE',
                'message' => trans('validation.NO_MATCH_SIGNATURE'),
            ]);
        }
        $request['sessionKey'] = $session_key;
        $response = $next($request);
        return $response;
    }
}