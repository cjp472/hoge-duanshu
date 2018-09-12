<?php
/**
 * 店铺认证到期状态改动
 */
namespace App\Http\Middleware;

use App\Models\ShopDisable;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ShopVerify
{
    public function handle($request, Closure $next)
    {
        if ($request->shop_id){
            $shopid = $request->shop_id;
        }else{
            $shop = Session::get('shop:'.Auth::id());
            $shopid = $shop ? $shop['id'] : '';
        }

        if($shopid)
        {
//            $verify = Shop::where('hashid',$shopid)->select('verify_expire','verify_status')->first();
//            if($verify->verify_status == 'invalid' && $verify->verify_expire>0){
//                $expire = $verify->verify_expire;
//                if(Auth::id()){
//                    if(time() > strtotime('+7 days',$expire))
//                    {
//                        return response([
//                            'error'     => 'shop-closed-invalid',
//                            'message'   => trans('validation.shop-closed-invalid'),
//                        ]);
//                        //店铺认证到期,打烊
//                    }
//                }else{
//                    if(strtotime('+7 days',$expire)>time() && $expire<time())
//                    {
//                        return response([
//                            'error'     => 'shop-protected',
//                            'message'   => trans('validation.shop-protected'),
//                        ]);
//                        //店铺认证到期,暂不支持交易
//                    }
//                }
//            }
            if(ShopDisable::isShopDisable($shopid)){
                return response([
                    'error'     => 'shop-protected',
                    'message'   => trans('validation.shop-protected'),
                ]);
            }
        }
        return $next($request);
    }
}