<?php

namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $hidden = [
        'password', 'remember_token','shop'
    ];

    public function shop()
    {
        return $this->hasOne('App\Models\Manage\UserShop','user_id','id');
    }

    public static function shopVersion($shop_id){
        return UserShop::where('user_shop.shop_id',$shop_id)->leftJoin('version_order as vo','vo.shop_id','=','user_shop.shop_id')->get(['vo.*']);
    }


}
