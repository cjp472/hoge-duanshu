<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopFlow extends Model
{
    protected $table = 'shop_flow';

    protected $fillable = ['shop_id', 'numberical', 'remark', 'time', 'unit_price', 'price', 'flow_type', 'qcloud_type','created_at','updated_at','source'];
}
