<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $connection = 'mysql';

    protected $table = 'banner';

    public $timestamps = false;

    public $hidden = ['create_user','update_user'];

}
