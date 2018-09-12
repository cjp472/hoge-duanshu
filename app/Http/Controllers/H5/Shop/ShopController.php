<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/4/11
 * Time: 17:14
 */

namespace App\Http\Controllers\H5\Shop;


use App\Http\Controllers\H5\BaseController;
use App\Models\Share;
use App\Models\Shop;
use App\Models\ShopColor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ShopController extends BaseController
{
    /**
     * 查看店铺信息和分享信息
     * @return mixed
     */
    public function info()
    {
        $shop = Shop::where(['hashid' => $this->shop['id']])
            ->select(['id','hashid','title', 'brief', 'status', 'h5_host',  'copyright', 'version', 'applet_version', 'applet_ios_pay', 'enable_customer_service'])
            ->first();
        if (!$shop) {
            $this->error('');
        }
        $shop->indexpic = $shop->indexpic();
        $shop->color = ShopColor::shopColor($this->shop['id']) ?: [];
        $shop->copyright = ['show' => intval($shop->copyright)];
        $share = Share::where(['shop_id' => $this->shop['id']])->first();
        if ($share) {
            $share->indexpic = hg_unserialize_image_link($share->indexpic);
            $share->makeHidden(['shops', 'id']);
            $shop->share = $share;
        }
        $shop->makeHidden(['hashid']);
        return $this->output($shop);
    }

}