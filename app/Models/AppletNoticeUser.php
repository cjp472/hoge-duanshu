<?php
/**
 * Created by PhpStorm.
 * User: a123456
 * Date: 2017/6/1
 * Time: 上午10:37
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class AppletNoticeUser extends Model
{
    protected $table = 'applet_notice_user';

    public $timestamps = false;

    protected $fillable = ['shop_id','notice_id','is_read'];

}