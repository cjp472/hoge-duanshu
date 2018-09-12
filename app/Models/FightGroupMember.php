<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/4/16
 * Time: 15:46
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class FightGroupMember extends Model
{
    protected $table = 'fightgroupmember';
    public $timestamps = false;

    /**
     * 关联order
     */
    public function order(){
        return $this->hasOne('App\Models\Order','order_id','order_no');
    }


}