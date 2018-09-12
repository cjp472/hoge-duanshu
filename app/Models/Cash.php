<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cash extends Model
{
    protected $connection = 'mysql';

    protected $table = 'cash';

    public $timestamps = false;

//    public function getCashTimeAttribute()
//    {
//        return $this->attributes['cash_time'] = date('Y-m-d H:i:s',$this->attributes['cash_time']);
//    }
}