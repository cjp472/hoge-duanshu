<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/21
 * Time: 下午4:31
 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MemberGag extends Model
{
    protected $table = 'member_gag';

    protected $fillable = [
        'shop_id','member_id','content_id','content_type','is_gag'
    ];


}