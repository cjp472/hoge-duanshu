<?php
/**
 * Created by PhpStorm.
 * User: huang an
 * Date: 2017/3/29
 * Time: 15:01
 */

namespace App\Models\Manage;


use Illuminate\Database\Eloquent\Model;

class Column extends Model
{

    protected $connection = 'mysql';

    protected $table = 'column';

    public $timestamps = false;


}