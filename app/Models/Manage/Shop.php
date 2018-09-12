<?php

namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $table = 'shop';
    public $timestamps = false;

    /**
     * 店铺版本信息
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function shopVersion(){
        return $this->hasMany('App\Models\Manage\VersionOrder','shop_id','shop_id');
    }

    public static function shopUser($shop_id){
        $user_shop = UserShop::where('admin',1)->where('shop_id',$shop_id)->first();
        return $user_shop ? $user_shop->user : [];
    }

    public function shopMultiple(){
        return $this->hasOne('App\Models\Manage\ShopMultiple','shop_id','hashid');
    }

    public function shopVersionExpire($shop_id){
        $version = VersionExpire::where([
            'hashid'=>$shop_id,
            'version'=> VERSION_ADVANCED
        ])->orderBy('expire','desc')->first();
        return $version ? $version->expire : 0;
    }

}