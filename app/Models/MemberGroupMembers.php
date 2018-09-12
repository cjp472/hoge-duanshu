<?php

/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/21
 * Time: 下午4:31
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberGroupMembers extends Model
{
  protected $table = 'members_groupmembers';
  protected $connection = 'djangodb';


  static function membersGroups($membersId) {
    return parent::whereIn('member_id', $membersId)
          ->join('members_group', 'members_groupmembers.group_id', '=', 'members_group.id')
          ->select('members_groupmembers.member_id', 'members_group.name', 'members_group.id')
          ->get()
          ->groupBy('member_id')
          ->toArray()
          ;

  }


}