<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopScore extends Model
{
    protected $table = 'shop_score';

    protected $fillable = ['shop_id', 'order_id', 'order_type', 'order_price', 'order_time', 'score', 'project', 'order_status', 'created_at', 'updated_at','last_score','source'];
}
