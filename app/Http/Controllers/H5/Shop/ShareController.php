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

class ShareController extends BaseController
{
    /**
     * 查看分享信息
     * @return mixed
     */
    public function detail()
    {
//        $result = Cache::get('share:' . $this->shop['id']);
//        if ($result) {
//            return $this->output(json_decode($result));
//        } else {
            $share = Share::where(['shop_id' => $this->shop['id']])->first();
            $shop = Shop::where(['hashid' => $this->shop['id']])->first();
            $shop_info = $shop ? [
                'title' => $shop->title,
                'brief' => $shop->brief,
                'status' => $shop->status,
                'version' => $shop->version,
                'color' => ShopColor::shopColor($this->shop['id']) ?: [],
            ] : [];
            if (!$share) {
                return $this->output(['shop' => $shop_info]);
            }
            $share->copyright = ['show'=>intval($shop->copyright)];
            $share->shop = $shop_info;
            $share->indexpic = hg_unserialize_image_link($share->indexpic);
            $share->makeHidden(['shops', 'id']);
//            Cache::forever('share:'.$this->shop['id'],json_encode($share));
            return $this->output($share ?: []);
//        }

    }

    /*
     * 获取配色信息
     */
    private function getColor(){
        $color = ShopColor::where(['shop_color.shop_id' => $this->shop['id']])
            ->leftJoin('color_template','color_template.id','=','shop_color.color_id')
            ->select('color_template.id','color_template.title','color_template.color','color_template.indexpic','shop_color.type')
            ->get();
        $arr = [];
        if($color){
            foreach ($color as $item){
                $arr[$item->type] = [
                    'id'       => $item->id,
                    'title'    => $item->title,
                    'color'    => $item->color,
                    'indexpic' => $item->indexpic,
                ];
            }
        }
        return $arr;
    }

}