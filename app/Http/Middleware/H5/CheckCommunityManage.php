<?php

namespace App\Http\Middleware\H5;

use App\Models\Community;
use App\Models\CommunityUser;
use Closure;
use Illuminate\Support\Facades\Route;

class CheckCommunityManage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
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
        $community_user = CommunityUser::where([
            'shop_id'=>$request->shop_id,
            'community_id'=>$request->community_id,
            'member_id'=>$sign['id']
        ])->value('role');
        $community_auth = Community::where([
            'shop_id'=>$request->shop_id,
            'hashid'=>$request->community_id,
        ])->value('authority');
        if($community_user != 'admin'){
            $check_route = ['communityMemberGag'];
            if($community_auth == 'admin' || in_array(Route::currentRouteName(),$check_route)){
                return response([
                    'error'     => 'no-community-manage',
                    'message'   => trans('validation.no-community-manage'),
                ]);
            }

        }
        return $next($request);
    }
}
