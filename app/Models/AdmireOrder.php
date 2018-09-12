<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmireOrder extends Model
{
    protected $table = 'admire_order';

    public $timestamps = false;

    /**
     * 对应内容关联模型
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function content(){
        return $this->hasOne('App\Models\Content','hashid','content_id');
    }

}
