<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/3/6
 * Time: 15:48
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    protected $table = 'community';

    protected $hidden = ['hashid','communityNote','communityUser'];


    /**
     * 关联社群成员
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function communityUser(){
        return $this->hasMany('App\Models\CommunityUser','community_id','hashid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 关联社群帖子
     */
    public function communityNote(){
        return $this->hasMany('App\Models\CommunityNote','community_id','hashid');
    }



}