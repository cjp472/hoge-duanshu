<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopContentRemind extends Model
{
    public $timestamps = false;

    protected $table = 'shop_content_remind_record';

    public function live(){
        return $this->hasOne('App\Models\Alive','content_id','content_id');
    }
}
