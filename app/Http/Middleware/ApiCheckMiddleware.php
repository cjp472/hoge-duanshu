<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Route;

class ApiCheckMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if($request->token){
            switch (Route::currentRouteName()){
                //小程序下单
                case 'orderMake':
                    $request->replace([
                        "content_id"=>"4j845362mk51",
                        "content_type"=>"article",
                        "shop_id"=>"83j2gb0e6356758d14",
                        "version"=>"advanced",
                    ]);

                    break;
                //h5端下单
                case 'makeOrder':
                    $request->replace([
                        "content_id"=>"4j845362mk51",
                        "content_type"=>"article",
                        "shop_id"=>"83j2gb0e6356758d14",
                        "version"=>"advanced",
                    ]);
                    break;
                //验证是否支付
                case 'isPay':

                //获取小程序授权sessionKey
                case 'WXAppletSessionKey':

                    break;
                //获取小程序授权登录
                case 'wxAppletLogin':
                    break;
                //获取私密账号设置信息
                case 'checkPrivateSettings':
                    break;
                //移动端绑定手机号
                case 'h5UserMobileBind':
                    $request->replace([
                        'mobile'=>'18356610596',
                        'code'  => '1234'
                    ]);
                    break;
                //获取直播消息列表
                case 'messageLists':
                    $request->replace([
                        'content_id'=> '24k951mj2knj'
                    ]);
                    break;
                //获取直播列表
                case 'aliveList':
                    break;
                //私密会员登录
                case 'userLogin':
                    $request->replace([
                        'username' => 'username',
                        'password'  => 'password'
                    ]);
                    break;

            }
            $request->offsetSet('shop_id','83j2gb0e6356758d14');
        }
        return $next($request);
    }
}
