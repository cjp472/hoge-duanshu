<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/9/25
 * Time: 10:02
 */

namespace App\Http\Controllers\Manage\Shop;


use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Color;
use App\Models\Manage\ShopColor;

class ColorController extends BaseController
{

    /**
     * 新增配色模板
     */
    public function createOrUpdate(){
        $this->validateWithAttribute([
            'color' => 'required',
            'title' => 'alpha_dash',
            'type'  => 'required|in:h5,applet',
            'class'  => 'required|alpha',
        ],[
            'color' => '颜色',
            'title' => '配色模板标题',
            'type'  => '配色应用场景',
            'class' => '颜色对应值'
        ]);
        if(request('id')){
            $color = Color::FindOrFail(request('id'));
        }else{
            $color = new Color();
        }
        $color->color = request('color');
        $color->title = trim(request('title'));
        $color->type = request('type');
        $color->class = request('class');
        $color->save();
        return $this->output($color);
    }

    /**
     * 配色列表
     */
    public function lists(){
        $color = Color::select('id','title','type','color','class','create_time')->orderBy('order_id','asc');
        request('type') && $color = Color::where('type',request('type'));
        $colors = $color->get();
        return $this->output($colors);
    }

    /**
     * 删除配色
     * @return mixed
     */
    public function delete(){
        $this->validateWithAttribute(['id' => 'required|numeric','type' => 'required|in:h5,applet'],['id' => '配色模板id','type' => '配色类型']);
        $color = Color::where(['id' => request('id'),'type' => request('type')])->firstOrFail();
        $flag = $color->delete();
        if($flag){
            ShopColor::where(['color_id' => request('id'),'type' => request('type')])->delete();
        }
        return $this->output(['success' => 1]);
    }

    /**
     * 配色排序
     * @return mixed
     */
    public function colorOrder(){
        $this->validateWithAttribute(['id' => 'required|regex:/^[0-9]\d*(,\d*)*$/','type' => 'required|in:h5,applet'],['id'=>'配色模板id','type' => '配色类型']);
        $ids = explode(',',request('id'));
        foreach ($ids as $key => $value){
            Color::where(['id' => $value,'type' => request('type')])
                ->update(['order_id' => $key]);
        }
        return $this->output(['success' => 1]);
    }

}