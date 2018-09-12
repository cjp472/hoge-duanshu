<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2017/8/9
 * Time: 10:21
 */

namespace App\Models\Manage;


use Illuminate\Database\Eloquent\Model;

class ShopMultiple extends Model
{
    protected $table = 'shop_multiple';
    public $timestamps = false;
    protected $fillable = ['shop_id'];

}