<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/3/6
 * Time: 15:52
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class CommunityUser extends Model
{
    protected $table = 'community_user';
    public $hidden = ['member','community','note'];

    /**
     * 关联member
     */
    public function member(){
        return $this->hasOne('App\Models\Member','uid','member_id');
    }

    /**
     * 关联community
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function community(){
        return $this->hasOne('App\Models\Community','hashid','community_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * 关联帖子
     */
    public function note(){
        return $this->hasMany('App\Models\CommunityNote','create_id','member_id');
    }

}