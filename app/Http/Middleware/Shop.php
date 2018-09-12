<?php
/**
 * 获取当前店铺信息
 */
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;

class Shop
{
    public function handle($request, Closure $next)
    {
        $user_id = Auth::id();
        if(!$user_id){
            return response([
                'error'     => 'no-login',
                'message'   => trans('validation.no-login'),
            ]);
        }

        $shop = Session::get('shop:'.$user_id);
        if(!Session::has('shop:'.$user_id) || !$shop){
            return response([
                'error'     => 'no-shop',
                'message'   => trans('validation.no-shop'),
            ]);
        }
        if(Cache::pull('change:'.$user_id)){
            hg_shop_response($user_id,true);
        }
        $shops = Redis::smembers('black:shop');
        if(in_array($shop['id'],$shops)){
            return response([
                'error'     => 'shop-locked',
                'message'   => trans('validation.shop-locked'),
            ]);
        }
        return $next($request);
    }
}