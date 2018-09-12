<?php
/**
 * 店铺信息
 */
namespace App\Http\Middleware\H5;

use App\Events\CurlLogsEvent;
use App\Jobs\ShopUpdate;
use App\Models\Shop;
use App\Models\ShopDisable;
use Closure;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class ShopCheck
{
    public function handle($request, Closure $next)
    {
        $shop_id = $request->get('shop_id');
        if(!$shop_id){
            return response([
                'error'     => 'no-shop-id',
                'message'   => trans('validation.required',['attribute' => '店铺id']),
            ]);
        }
        $shops = Redis::smembers('black:shop');
        if(in_array($shop_id,$shops)){
            return response([
                'error'     => 'shop-locked',
                'message'   => trans('validation.shop-locked'),
            ]);
        }
        if(ShopDisable::isShopDisable($shop_id)){
            return response([
                'error'     => 'shop-closed',
                'message'   => trans('validation.shop-closed'),
            ]);
        }
//        $close_shops = Redis::smembers('close:shop');
//        if(in_array($shop_id,$close_shops)){ //店铺打烊
//            return response([
//                'error'     => 'shop-closed',
//                'message'   => trans('validation.shop-closed'),
//            ]);
//        }
//        $shop = json_decode(Cache::get('shop:'.$shop_id));
//        if(!$shop){
            $shop = Shop::where('hashid',$shop_id)->first();
//            Cache::forever('shop:'.$shop_id,json_encode($shop));
//        }
        if(!$shop || !$shop->id){
            return response([
                'error'     => 'no-shop',
                'message'   => trans('validation.no-shop'),
            ]);
        }

        $is_production = (env('APP_ENV') == 'production' || env('APP_ENV') == 'test')? 1 : 0;
        $mch_id =  $is_production ? $shop->mch_id : (isset($shop->test_mch_id) ? $shop->test_mch_id :'');
        if (!$mch_id && (Route::currentRouteName() == 'makeOrder' || Route::currentRouteName() == 'admireOrder')) {
            $res = $this->create_merchant($shop_id);
            if ($res['response']->getStatusCode() !== 200) {
                return response([
                    'error' => 'create-merchant-error',
                    'message' => trans('validation.create-merchant-error'),
                ]);
            }
            $ret = $res['response']->getBody()->getContents();
            event(new CurlLogsEvent($ret, $res['client'], config('define.pay.plat.register_mch')));

            if ($ret) {
                $return = json_decode($ret);
                if ($return && $return->error_code && $return->error_code != 40001) {
                    return response([
                        'error' => 'pay-error-' . $return->error_code,
                        'message' => $return->error_message,
                    ]);
                }
                if (($return && isset($return->result) && isset($return->result->mch_id)) || $return->error_code == 40001) {
                    if($is_production) {
                        $shop->mch_id = isset($return->result->mch_id) ? $return->result->mch_id : 200000000145;
                    }else {
                        $shop->test_mch_id = isset($return->result->mch_id) ? $return->result->mch_id : 200000000145;
                    }
                    Cache::forever('shop:'.$shop_id,json_encode($shop));
                    $mch_id = $is_production ? $shop->mch_id : $shop->test_mch_id;
                    dispatch((new ShopUpdate($shop_id,$mch_id))->onQueue(DEFAULT_QUEUE));
                }
            }
        }
        $request->merge(['mch_id'=>$mch_id]);
        return $next($request);
    }

    private function create_merchant($shop_id)
    {
        $param = [
            'user_id' => $shop_id,
        ];
        $signature = hg_pay_signature(json_encode($param));
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-signature' => $signature['sign'],
                'x-api-key' => $signature['access_key'],
                'x-api-timestamp' => $signature['timestamp'],
            ],
            'body'  => json_encode($param),
        ]);
        $res = $client->request('POST',config('define.pay.plat.register_mch'));
        return ['client'=>$client,'response'=>$res];
    }
}