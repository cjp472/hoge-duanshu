<?php

namespace App\Listeners;

use App\Events\JoinCommunityEvent;
use App\Models\Community;
use App\Models\CommunityUser;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class JoinCommunity
{


    /**
     * Handle the event.
     *
     * @param  JoinCommunityEvent  $event
     * @return void
     */
    public function handle(JoinCommunityEvent $event)
    {
        $info = $event->data;
        $community_id = isset($info['community_id']) ? $info['community_id']: '';
        $member_id = isset($info['member_id']) ? $info['member_id']: '';
        if($community_id && $member_id){
            $community = Community::where(['hashid' => $community_id])->first();
            //判断该社群存在
            if ($community) {
                $community_user = CommunityUser::where(['community_id' => $community_id, 'member_id' => $member_id])->first();
                $role = isset($info['role']) ? $info['role']: '';
                if ($role == 'admin' && !($community_user && $community_user->role == 'admin')) {
                    CommunityUser::where(['community_id' => $community_id, 'role' => 'admin'])->update(['role'=>'member']);
                }
                //如果没加入社群，进行加入操作
                if (!$community_user) {
                    $community_user = new CommunityUser();
                    $community_user->setRawAttributes($info);
                    $community_user->save();
                    $community->increment('member_num');
                } else {
                    $community_user->expire = isset($info['expire']) ? $info['expire']: 0;
                    $source = isset($info['source']) ? $info['source']: '';
                    if($source && ($source != 'admin_setting' || !$community_user->source)){
                        $community_user->source = $source;
                    }
                    if ($role == 'admin') {
                        $community_user->role = $role;
                    }

                    $community_user->save();
                }
            }
        }
    }
}
