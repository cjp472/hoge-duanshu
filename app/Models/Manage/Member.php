<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/29
 * Time: 15:03
 */

namespace App\Models\Manage;


use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $table = 'member';
    public $timestamps = false;

    public $visible = ['id','shop_id','avatar','nick_name','true_name','email','mobile','address','company','position','is_black','sex','count','language','province','source','value','name','openid','title'];


}