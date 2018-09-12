<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 16/8/10
 * Time: 上午11:54
 */
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;

class M2OCloudMiddleware
{
    public function handle($request, Closure $next)
    {

        $method = urldecode($request['method']);
        $ts = urldecode($request['ts']);
        $params = urldecode($request['params']);
        $signature = urldecode($request['signature']);
        $data = 'method='.$method.'&ts='.$ts.'&params='.$params;
        $client_secret = config('define.M2O.client_secret');
        $new_signature = str_replace(['+', '/'], ['-', '_'], base64_encode(hash_hmac('sha1', $data, $client_secret, true)));

        if (!$signature) {
            return response([
                'error' => 'no-signature',
                'message' => trans('validation.no-signature-param'),
            ]);
        }
        if ($signature != $new_signature) {
//            return response([
//                'error'     => 'no-match-signature',
//                'message'   => trans('validation.NO_MATCH_SIGNATURE'),
//            ]);
        }
        $request['params'] = $params;
        $response = $next($request);
        return $response;
    }
}
