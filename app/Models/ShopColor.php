<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/9/26
 * Time: 上午6:03
 */

namespace App\Models;



namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopColor extends Model
{
    public $timestamps = false;

    protected $table = 'shop_color';

    public static function shopColor($shop_id){
        return static::where('shop_id',$shop_id)
            ->join('color_template as ct','ct.id','=','shop_color.color_id')
            ->get(['ct.id','ct.type','ct.color','ct.class']);
    }
}
