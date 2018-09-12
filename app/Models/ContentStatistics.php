<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class ContentStatistics extends Model
{
    protected $table = 'content_statistics';
    protected $fillable = ['type', 'create_time', 'yesterday_income', 'click_num', 'order_num', 'shop_id', 'year', 'month', 'week','day'];
}