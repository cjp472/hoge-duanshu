<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/11/29
 * Time: 13:48
 */

namespace App\Http\Middleware;

use Closure;
use EasyWeChat\Support\XML;

class CheckWechatCallback
{
    public function handle($request, Closure $next)
    {
        if($request->getContentType() == 'xml'){
            $param = XML::parse($request->getContent());
            $request->merge(['param'=>$param]);

        }
        return $next($request);

    }

}