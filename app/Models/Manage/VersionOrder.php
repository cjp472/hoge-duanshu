<?php

namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;

class VersionOrder extends Model
{
    protected $table = 'version_order';

    public $timestamps = false;

    /**
     * 获取店铺管理员信息
     */
    public static function shopUser($shop_id){
        return UserShop::where('shop_id',$shop_id)
            ->where('admin',1)
            ->leftJoin('users','users.id','=','user_shop.user_id')
            ->leftJoin('shop','shop.hashid','user_shop.shop_id')
            ->leftJoin('regist_track', 'user_shop.user_id', '=', 'regist_track.user_id')
            ->first(['users.id','users.name','users.email','users.mobile','users.login_time','users.created_at','shop.agent','shop.channel']);
    }

}
