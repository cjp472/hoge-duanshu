<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBind extends Model
{
    protected $table = 'user_bind';
    public $timestamps = false;
    protected $fillable = ['openid','source','nickname','avatar','sex','user_id','create_time','bind_time','ip','agent','unionid'];
}