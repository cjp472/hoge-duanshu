<?php

namespace App\Http\Middleware;

use App\Models\ModulesShopModule;
use Closure;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Shop;

class PromotionShop
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
        $shop_id = Auth::id() ? Session::get('shop:'.Auth::id())['id'] : '';
        $shop = Shop::where(['hashid'=>$shop_id])->first();
        if(!$shop){
            return response([
                'error' => 'shop-not-found',
                'message' => trans('validation.shop-does-not-exist'),
            ]);
        }
        if(!ModulesShopModule::isModuleOpen($shop->id, ModulesShopModule::MODULE_SLUG_PROMOTION)){
            return response([
                'error' => 'not-open-promotion',
                'message' => trans('validation.not-open-promotion'),
            ]);
        }
        return $next($request);
    }
}
