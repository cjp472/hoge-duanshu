<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/7
 * Time: 15:01
 */

namespace App\Models\Manage;


use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{


    protected $table = 'comment';

    public $timestamps = false;

    protected $hidden = ['belongsToMember','belongsToContent','hasOneReply','content_id'];


    public function belongsToContent()
    {
        return $this->belongsTo('App\Models\Manage\Content','content_id','hashid');
    }

    public function belongsToMember(){
        return $this->belongsTo('App\Models\Manage\Member','member_id','uid');
    }

    public function hasOneReply(){
        return $this->hasOne('App\Models\Reply','comment_id','id');
    }





}