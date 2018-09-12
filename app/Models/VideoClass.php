<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoClass extends Model
{
    protected $connection = 'mysql';

    protected $table = 'video_class';

    public $timestamps = true;
}
