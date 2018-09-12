<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/9/6
 * Time: 上午10:29
 */
namespace  App\Models\Manage;
use Illuminate\Database\Eloquent\Model;

class AdmireOrder extends Model
{
    protected $table = 'admire_order';
    public $timestamps = false;
    protected $hidden = ['belongContent','belongShop'];

    public function belongContent()
    {
        return $this->belongsTo('App\Models\Manage\Content','content_id','hashid');
    }

    public function belongShop()
    {
        return $this->belongsTo('App\Models\Manage\Shop','shop_id','hashid');
    }
}