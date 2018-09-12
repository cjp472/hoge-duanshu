<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/7/7
 * Time: 上午9:17
 */
namespace App\Models\Manage;
use Illuminate\Database\Eloquent\Model;

class MemberMessage extends Model
{
    protected $table = 'member_notify';
    public $timestamps = false;
}