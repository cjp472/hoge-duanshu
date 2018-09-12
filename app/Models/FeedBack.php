<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/7
 * Time: 15:01
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class FeedBack extends Model
{


    protected $table = 'feedback';

    public $timestamps = false;

    protected $hidden = ['belongsToMember'];


    public function belongsToMember()
    {
        return $this->belongsTo('App\Models\Member','member_id','uid');
    }




}