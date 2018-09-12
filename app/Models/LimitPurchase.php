<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/21
 * Time: 下午4:31
 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LimitPurchase extends Model
{
    protected $table = 'limit_purchase';

    public static function serializer($limit_purchase)
    {
        $limit_purchase->indexpic = $limit_purchase->indexpic ? hg_unserialize_image_link($limit_purchase->indexpic) : '';
        if ($limit_purchase->start_time < time() && $limit_purchase->end_time > time()) {
            $limit_purchase->status = 1;
        } elseif ($limit_purchase->start_time > time()) {
            $limit_purchase->status = 0;
        } elseif ($limit_purchase->end_time < time()) {
            $limit_purchase->status = 2;
       }
        $limit_purchase->start_time = $limit_purchase->start_time ? hg_format_date($limit_purchase->start_time) : 0;
        $limit_purchase->end_time = $limit_purchase->end_time ? hg_format_date($limit_purchase->end_time) : 0;
        $limit_purchase->makeHidden('contents');
        return $limit_purchase;
}
}