<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/9/25
 * Time: 10:08
 */

namespace App\Models\Manage;


use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $table = 'color_template';

    public $timestamps = false;

    public $fillable = ['color','title'];

}