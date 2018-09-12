<?php
/**
 * 支付回调接口的中间件
 */

namespace App\Http\Middleware;

use Closure;

class checkPaySignature
{
    public function handle($request, Closure $next)
    {
        if($this->check_pay_signture($request)){
            return $next($request);
        }
        return response([
            'error'     => 'error-signature',
            'message'   => trans('validation.error-signature'),
        ]);
    }

    private function check_pay_signture($request)
    {
        $timestamp = $request->header('x-api-timestamp');
        $sign = $request->header('x-api-signature');
        $signs = hg_pay_signature($request->getContent(),$timestamp);
        $_sign = $signs['sign'];
        return $sign == $_sign ? true : false;
    }
}