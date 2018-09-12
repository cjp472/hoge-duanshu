<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopNotice extends AppEnvModel
{
    protected $table = 'shop_notice';

    protected $fillable = ['shop_id', 'type', 'content', 'status', 'env', 'created_at', 'updated_at'];

    public static function createShopNotice($shop_id, $type, $content) {
        $where = ['shop_id' => $shop_id, 'type' => $type, 'status' => 1];
        $count = ShopNotice::where($where)->count();
        if ($count == 0) {
            $shop_notice = new ShopNotice();
            $shop_notice->shop_id = $shop_id;
            $shop_notice->type = $type;
            $shop_notice->content = $content;
            $shop_notice->status = 1;
            $shop_notice->save();
        }
    }

    /**
     * 撤销通知
     * @param $shop_id
     * @param $type
     */
    public static function setShopNoticeCancel($shop_id, $type){
        $where = ['shop_id' => $shop_id, 'type' => $type, 'status' => 1];
        $shop_notice = ShopNotice::where($where)->first();
        if ($shop_notice) {
            $shop_notice->status = 0;
            $shop_notice->save();
        }
    }

    public static function getShopNoticeList($shop_id){
        $where = ['shop_id' => $shop_id, 'status' => 1];
        return self::where($where)->get();
    }

}
