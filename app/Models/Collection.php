<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/3/6
 * Time: 15:53
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collection';

    public $timestamps = false;

    public $hidden = ['note','member'];


    /**
     * 帖子
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function note(){
        return $this->hasOne('\App\Models\CommunityNote','hashid','content_id');
    }

    /**
     * 会员
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member(){
        return $this->hasOne('\App\Models\Member','hashid','member_id');
    }

}