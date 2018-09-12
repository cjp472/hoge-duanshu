<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sdk extends Model
{
    protected $table = 'sdk';

    public $timestamps = false;

    public function getIndexPicAttribute()
    {
        $this->attributes['index_pic'] = unserialize($this->attributes['index_pic']);
        return $this->attributes['index_pic'];
    }

    public function getPlatformAttribute()
    {
        $this->attributes['platform'] = unserialize($this->attributes['platform']);
        return $this->attributes['platform'];
    }
}