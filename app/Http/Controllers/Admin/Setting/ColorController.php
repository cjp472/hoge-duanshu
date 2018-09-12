<?php
/**
 * Created by PhpStorm.
 * Date: 2017/9/22
 * Time: 上午11:04
 */

namespace App\Http\Controllers\Admin\Setting;


use App\Http\Controllers\Admin\BaseController;
use App\Models\ColorTemplate;
use App\Models\OpenPlatformApplet;
use App\Models\ShopColor;
use Illuminate\Support\Facades\Cache;

class ColorController extends BaseController
{

    /**
     * 配色列表
     * @return mixed
     */
    public function lists(){
        $this->validateWithAttribute([
            'type'  => 'required|alpha_dash|in:h5,applet',
        ],[
            'type'  => '配色应用场景'
        ]);
        $color = ColorTemplate::where(['type'=>request('type')])
            ->select('id','title','color','type')
            ->orderBy('order_id')
            ->get();
        $color = $color ? : [];
        return $this->output($color);
    }

    /**
     * 选择配色
     * @return mixed
     */
    public function chooseH5Color()
    {
        $this->validateWithAttribute([
            'id' => 'required|numeric',
            'type' => 'required|in:h5,applet'
        ], [
            'id' => '颜色模板ID',
            'type' => '类型']
        );
        Cache::forget('share:'.$this->shop['id']);
        $shop = ShopColor::where(['shop_id' => $this->shop['id'],'type' => request('type')])->first();
        if($shop){ //存在，更新
            $shop->color_id = request('id');
            $shop->save();
            return $this->output($shop);
        }else{ //不存在，新增
            $color = new ShopColor();
            $color->shop_id = $this->shop['id'];
            $color->color_id = request('id');
            $color->type = request('type');
            $color->save();
            return $this->output($color);
        }
    }

    /**
     * 小程序选择配色
     * @return mixed
     */
    public function chooseAppletColor()
    {
        $this->validateWithAttribute([
            'id' => 'required|numeric',
            'type' => 'required|in:h5,applet'
        ], [
                'id' => '颜色模板ID',
                'type' => '类型']
        );
        Cache::forget('share:'.$this->shop['id']);
        $color = ShopColor::where(['shop_id' => $this->shop['id'],'type' => request('type')])->first();
        if($color){ //存在，更新
            $color->color_id = request('id');
            $color->save();
        }else{ //不存在，新增
            $color = new ShopColor();
            $color->shop_id = $this->shop['id'];
            $color->color_id = request('id');
            $color->type = request('type');
            $color->save();
        }
        return $this->output($color);
    }


}