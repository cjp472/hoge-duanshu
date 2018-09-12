<?php

namespace App\Http\Middleware;

use Closure;

class ClientApiCheckMiddleware
{
    /**
     * 验证短书客户端请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if($this->check_sign($request)){
            return $next($request);
        }

        return response([
            'error'     => 'error-signature',
            'message'   => trans('validation.error-signature'),
        ]);
    }

    private function check_sign($request){
        $sign = $request->header('x-api-signature');
        $param = [
            'secret'=>config('define.duanshu_client.client_secret'),
            'timestamp' => $request->header('x-api-timestamp'),
            'shop_id' => $request->header('x-shop-id'),
        ];
        $string = 'secret={secret}&timestamp={timestamp}&shop_id={shop_id}';
        $sign_string = str_replace(['{secret}','{timestamp}','{shop_id}'],array_values($param),$string);
        $signature = strtoupper(sha1(md5($sign_string)));
        return $signature == $sign ? true : false;

    }
}
