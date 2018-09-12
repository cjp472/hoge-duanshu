<?php

namespace App\Http\Middleware\H5;

use App\Models\ModulesShopModule;
use App\Models\Shop;
use Closure;

class CommunityCheck
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
        $is_community_open =ModulesShopModule::isModuleOpen($shop->id, ModulesShopModule::MODULE_SLUG_COMMUNITY);
        if(!$is_community_open){
            return response([
                'error' => 'no-open-community',
                'message'   => trans('validation.no-open-community')
            ]);
        }
        return $next($request);
    }
}
