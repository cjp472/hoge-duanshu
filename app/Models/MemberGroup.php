<?php

/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/21
 * Time: 下午4:31
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberGroup extends Model
{
  protected $table = 'members_group';
  protected $connect = 'djangodb';


  static function groupsMembers($groupsId) {
    return parent::selectRaw('distinct member_id')->whereIn('group_id', $groupsId)->get()->pluck('member_id')->toArray();
  }


}