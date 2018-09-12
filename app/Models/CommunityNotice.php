<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/3/6
 * Time: 15:50
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class CommunityNotice extends Model
{
    protected $table = 'community_notice';

    protected $hidden = ['hashid'];
}