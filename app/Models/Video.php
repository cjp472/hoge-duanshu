<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $connection = 'mysql';

    protected $table = 'video';

    public $timestamps = false;

    public $hidden = ['hashid'];

    public function videoInfo(){
        return $this->hasOne('App\Models\Videos','file_id','file_id');
    }

    public function testVideoInfo(){
        return $this->hasOne('App\Models\Videos','file_id','test_file_id');
    }

}
