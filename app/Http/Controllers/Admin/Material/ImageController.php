<?php
/**
 * 图片管理
 */
namespace App\Http\Controllers\Admin\Material;

use App\Http\Controllers\Admin\BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ImageController extends BaseController
{
    public function signature()
    {
        $key = 'upload:signature';
        if((!$sign = Cache::get($key)) || (Redis::ttl(config('cache.prefix').':'.$key) <= 30)){
            $appid = config('qcloud.appid');
            $bucket = config('qcloud.cos.bucket');
            $secret_id = config('qcloud.secret_id');
            $secret_key = config('qcloud.secret_key');
            $expired = time() + config('qcloud.cos.signature_expire_time');
            $current = time();
            $rdm = rand();

            $multi_effect_signature = 'a='.$appid.'&b='.$bucket.'&k='.$secret_id.'&e='.$expired.'&t='.$current.'&r='.$rdm.'&f=';
            $sign = base64_encode(hash_hmac('SHA1', $multi_effect_signature, $secret_key, true).$multi_effect_signature);
            Cache::put($key,$sign,config('qcloud.cos.signature_expire_time')/60);
        }
        return $this->output(['sign'=>$sign]);
    }

}