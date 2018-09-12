<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audio extends Model
{
    protected $connection = 'mysql';

    protected $table = 'audio';

    public $timestamps = false;

    public $hidden = ['hashid'];

}
