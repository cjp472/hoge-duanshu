<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admire extends Model
{
    protected $table = 'admire';

    public $timestamps = false;

    protected $hidden = ['belongLive','belongMember','belongLecturer'];

    public function belongLive()
    {
        return $this->belongsTo('App\Models\Content','content_id','hashid');
    }

    public function belongMember()
    {
        return $this->belongsTo('App\Models\Member','member_id','uid');
    }

    public function belongLecturer()
    {
        return $this->belongsTo('App\Models\Member','lecturer','uid');
    }

}
