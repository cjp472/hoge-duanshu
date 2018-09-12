<?php

namespace App\Http\Middleware\H5;

use App\Models\CommunityUser;
use Closure;

class CheckJoinCommunity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $sign = $request->memberInfo;
        if(!$sign || !$sign['id']){
            return response([
                'error'     => 'no-member-info',
                'message'   => trans('validation.no-member-info'),
            ]);
        }

        $member_id = hg_is_same_member($sign['id'],$request->shop_id);
        $community_user = CommunityUser::where(['shop_id' => $request->shop_id, 'community_id' => $request->community_id])->whereIn('member_id',$member_id)->first();

        if(!$community_user){
            return response([
                'error'     => 'no-community-user',
                'message'   => trans('validation.no-community-user'),
            ]);
        }
        return $next($request);
    }
}
