<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/4/16
 * Time: 10:03
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class FightGroupActivity extends Model
{
    protected $table = 'fightgroupactivity';

    protected $keyType = 'string';

    public $timestamps = false;

}