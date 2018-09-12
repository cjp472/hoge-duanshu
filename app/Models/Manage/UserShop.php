<?php
namespace App\Models\Manage;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserShop extends Model
{
    protected $table = 'user_shop';
    public $timestamps = false;

    public $hidden = ['id','user'];
    public function user(){
        return $this->hasOne('App\Models\Manage\Users','id','user_id');
    }

    public static function userShop($user_id)
    {
        return static::where('user_id',$user_id)
            ->join('shop', 'user_shop.shop_id', '=', 'shop.hashid')
            ->first();
    }
}