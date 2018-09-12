<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class ShopProtocol extends Model
{
    protected $table = 'shop_protocol';
    public $timestamps = false;

    public function getCreateTimeAttribute()
    {
        return $this->attributes['create_time'] = date('Y-m-d H:i:s',$this->attributes['create_time']);
    }

    public function getContentAttribute()
    {
        return $this->attributes['content'] = unserialize($this->attributes['content']);
    }
}