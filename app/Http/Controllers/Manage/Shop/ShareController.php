<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/6/30
 * Time: 上午10:13
 */
namespace App\Http\Controllers\Manage\Shop;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Share;
use App\Models\Manage\Shop;

class ShareController extends BaseController
{
    /**
     * 店铺分享信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopShare()
    {
        $this->validateWith([
            'shop_id'   =>  'required|alpha_dash' ,
        ]);
        $share = Share::where('shop_id',request('shop_id'))
            ->select('id','title','brief','indexpic','qrcode')
            ->first();
        if ($share) {
            $share->indexpic = $share->indexpic ? hg_unserialize_image_link($share->indexpic) : '';
        }
        return $this->output($share?:[]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 店铺授权信息展示开关
     */
    public function checkCopyrightShow(){
        $this->validateWithAttribute(['shop_id'=>'required|alpha_dash','show'=>'required'],['shop_id'=>'店铺id','show'=>'是否展示']);
        $shop = Shop::where(['hashid'=>request('shop_id')])->first();
        $shop->copyright = intval(request('show'));
        $shop->save();
        return $this->output(['success'=>1]);
    }
}