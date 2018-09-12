<?php

namespace App\Http\Middleware\H5;

use App\Models\ModulesShopModule;
use App\Models\Shop;
use Closure;
use Illuminate\Support\Facades\Route;

class PromotionCheck
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
        $shop_id = $request->shop_id;
        $shop = Shop::where(['hashid' => $shop_id])->first();
        if (!$shop) {
            return response([
                'error' => 'shop-not-found',
                'message' => trans('validation.shop-does-not-exist'),
            ]);
        }
        $is_promotion_open =ModulesShopModule::isModuleOpen($shop->id, ModulesShopModule::MODULE_SLUG_PROMOTION);
        if (!$is_promotion_open && !in_array(Route::currentRouteName(), ['basicInfo'])) {
            return response([
                'error' => 'not-open-promotion',
                'message' => trans('validation.not-open-promotion'),
            ]);
        }

        return $next($request);
    }
}
