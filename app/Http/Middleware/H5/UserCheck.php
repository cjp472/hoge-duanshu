<?php
/**
 * 根据会员信息查询用户信息
 */

namespace App\Http\Middleware\H5;

use App\Models\Member;
use App\Models\UserBind;
use Closure;

class UserCheck
{

    public function handle($request,Closure $next){

//        $member = $request->header('x-member');
//        $member = json_decode(urldecode($member),1);
//        $union_id = Member::where('uid',$member['id'])->value('union_id');
//        $user = UserBind::where('unionid',$union_id)->first();
//
//        $request->merge(['user_id'=>$user->id]);

        return $next($request);
    }

}