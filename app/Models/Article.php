<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $connection = 'mysql';

    protected $table = 'article';

    public $timestamps = false;

    public $hidden = ['hashid'];

}
