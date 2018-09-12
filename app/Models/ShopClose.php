<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopClose extends Model
{
    protected $table = 'shop_close';

    public static function createOrDoNotExistShopClose($shop_id, $method, $reason, $expire_time){
        $where = ['shop_id'=>$shop_id, 'method' => $method, 'reason' => $reason, 'process' => 0];
        $count = ShopClose::where($where)->count();
        if ($count == 0) {
            $sc = new ShopClose();
            $sc->shop_id = $shop_id;
            $sc->method = $method;
            $sc->reason = $reason;
            $sc->event_time = time();
            $sc->process_time = $expire_time;
            $sc->save();
        }
    }
}