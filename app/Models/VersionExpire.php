<?php
/**
 * Created by PhpStorm.
 * User: Janice
 * Date: 2018/1/8
 * Time: 下午5:21
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VersionExpire extends Model
{
    protected $table = 'version_expire';

    protected $fillable = ['is_expire'];
}