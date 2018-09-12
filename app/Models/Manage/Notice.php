<?php

namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $table = 'system_notice';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['shop'];

    public function shop()
    {
        return $this->belongsTo('App\Models\Manage\Shop','shop_id','hashid');
    }
}
