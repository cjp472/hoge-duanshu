<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AliveMessage extends Model
{
    protected $connection = 'mysql';

    protected $table = 'live_message';

    public $timestamps = false;

    public $hidden = ['member'];

    public $fillable = ['problem_state'];

    public function alive(){
        return $this->hasOne('App\Models\Alive','content_id','content_id');
    }

    public function content(){
        return $this->hasOne('App\Models\Content','hashid','content_id')->where('type','live');
    }

    public function member(){
        return $this->hasOne('App\Models\Member','uid','member_id');
    }

}
