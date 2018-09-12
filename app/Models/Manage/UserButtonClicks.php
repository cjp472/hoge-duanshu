<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/1/10
 * Time: 14:36
 */

namespace App\Models\Manage;


use Illuminate\Database\Eloquent\Model;

class UserButtonClicks extends Model
{
    protected $table = 'user_button_click';
    public $timestamps = false;

    public function user(){
        return $this->hasOne('App\Models\Manage\Users','id','user_id');
    }

    public function shop(){
        return $this->hasOne('App\Models\Manage\Shop','hashid','shop_id');
    }

}