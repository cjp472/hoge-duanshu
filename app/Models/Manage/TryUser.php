<?php

namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;

class TryUser extends Model
{

    protected $connection = 'mysql';

    protected $table = 'try_user';

    public $timestamps = false;
}
