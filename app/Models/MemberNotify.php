<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/30
 * Time: 10:41
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class MemberNotify extends Model
{
    protected $table = 'member_notify';
    public $timestamps = false;
    protected $fillable = ['member_id','is_read','notify_id','is_ignored','ignore_time'];

}