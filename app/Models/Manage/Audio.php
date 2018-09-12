<?php

namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;

class Audio extends Model
{

    protected $table = 'audio';

    public $timestamps = false;

    public $hidden = ['hashid'];

}
