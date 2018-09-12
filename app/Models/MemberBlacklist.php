<?php

/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/21
 * Time: 下午4:31
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Member;

class MemberBlacklist extends Model
{
  protected $table = 'members_blacklist';
  protected $connection = 'djangodb';

  static function shopBlacklist($shopPk)
  {
    return parent::where('shop_id',$shopPk)->get();
  }

  static function isBlackMember($shopPk, $member)
  {
      if(!$member){
          return [];
      }
      $membersUid = [$member->uid];
      $membersPk = [$member->id];
      // if ($member->mobile) {
      //     $membersPk = Member::where(['shop_id' => $member->shop_id, 'shop' => $member->shop_id])->whereIn('mobile', $member->mobile)->get()->pluck('id')->toArray();
      //   }
      $isBlack = parent::where('shop_id', $shopPk)->whereIn('member_id', $membersPk)->first();
      if ($isBlack) {
          return $membersUid;
      } else {
          return [];
      }
  }


}