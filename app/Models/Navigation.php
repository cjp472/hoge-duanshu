<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Navigation extends Model
{
    protected $table = 'navigation';
    public $timestamps = false;

    public function getCreateTimeAttribute()
    {
        $this->attributes['create_time'] = date('Y-m-d H:i:s',$this->attributes['create_time']);
        return $this->attributes['create_time'];
    }

//    public function getIndexPicAttribute()
//    {
//        $this->attributes['index_pic'] = unserialize($this->attributes['index_pic']);
//        return $this->attributes['index_pic'];
//    }
//
//    public function getLinkAttribute()
//    {
//        $this->attributes['link'] = unserialize($this->attributes['link']);
//        return $this->attributes['link'];
//    }
}