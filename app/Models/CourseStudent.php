<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;


class CourseStudent extends Model
{
    protected $table = 'course_student';

    const ENTRANCE = ['buy' => '购买', 'share_code' => '领取赠送', 'code' => '赠送码', 'sub' => '免费订阅','member_card_sub'=>'会员免费'];
    //1-支付购买  2- 赠送领取 3- 自建邀请码 4-免费订阅
    const PAYMENT_TYPE_TO_ENTRANCE = [1 => 'buy', 2 => 'share_code', 3 => 'code', 4 => 'sub',5=>'member_card_sub'];

    public static function verboseEntrance($entrance)
    {
        return array_key_exists($entrance, self::ENTRANCE) ? self::ENTRANCE[$entrance] : $entrance;
    }

    public static function studiedClassCount($member_id,$course_id)
    {
        $member = Member::where('uid',$member_id)->first();
        $membersUid = $member->getUnionUids();
        DB::enableQueryLog();

        $sub = ClassViews::where(['course_id' => $course_id])
                ->whereIn('member_id', $membersUid)->select('class_id')->distinct();
        $count = DB::table(DB::raw("({$sub->toSql()}) as sub"))
                ->mergeBindings($sub->getQuery())
                ->count();
        return $count;
    }
}
