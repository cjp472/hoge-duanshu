<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alive extends Model
{
    protected $connection = 'mysql';

    protected $table = 'live';

    public $timestamps = false;

    public function content_type(){
        return $this->hasMany('App\Models\ContentType','content_id','content_id');
    }

    public function videoInfo(){
        return $this->hasOne('App\Models\Videos','file_id','file_id');
    }
    static public function getVidepByIds($liveIds)
    {
        return static::whereIn('content_id',$liveIds)->get();
    }
}
