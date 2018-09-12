<?php
/**
 * 会员处理基类
 */
namespace App\Http\Controllers\H5\Client;

use App\Http\Controllers\H5\BaseController as BController;
use App\Jobs\WechatMemberUpdate;
use App\Models\Member;
use App\Models\MemberBlacklist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BaseController extends BController
{
    protected function memberExist($where = [])
    {
        $member = Member::where($where)->first();
        return $member;
    }

    protected function checkoutBlackMember($shopPk,$uid) {
        // 检出黑名单会员
        $member = $this->memberExist(['uid'=>$uid]);
        $memberBlacklist = MemberBlacklist::isBlackMember($shopPk,$member);
        if($memberBlacklist) {
            Redis::sadd($member->shop_id . ':black:member', $memberBlacklist);
        }
    }

    /**
     * 判断用户名和头像是否和数据库一致
     * @param $member
     * @param $user
     * @return bool
     */
    protected function checkAvatarAndName($member,$user)
    {
        if(($member->avatar != $user->getAvatar()) || ($member->nick_name != $user->getNickname())){
            $member->avatar = $user->getAvatar();
            $member->nick_name = (ctype_space($user->getNickname()) || !$user->getNickname())? DEFAULT_NICK_NAME : $user->getNickname();
            Cache::forever('wechat:member:'.$member->id,json_encode($member));
            dispatch((new WechatMemberUpdate($member->id,$user->getAvatar(),$user->getNickname()))->onQueue(DEFAULT_QUEUE));
        }
    }

    protected function getResponse($id,$user)
    {
        return [
            'id'            => $id,
            'nick_name'     => $user->getNickname()?: $user->getId(),
            'openid'        => $user->getId(),
            'avatar'        => $user->getAvatar(),
            'source'        => 'wechat',
        ];
    }

    protected function signature($response,$sign=1)
    {
        $timestamp = time();
        $randomStr = str_random(12);
        $data = $response ?: [];
        $sign = [
            'timestamp' => $timestamp,
            'randomstr' => $randomStr,
            'secret' => MEMBER_SECRET,
            'expire' => $timestamp + ($sign ? MEMBER_EXPIRE : PRIVATE_MEMBER_EXPIRE),
        ];
        $ret = array_merge($data,$sign);
        ksort($ret);
        $signurl = '';
        foreach($ret as $k=>$v)
        {
            $signurl .= $k.'='.$v.'&';
        }
        $signurl = trim($signurl,'&');
        $signature = sha1($signurl);
        $ret['signature'] = $signature;
//        unset($ret['secret']);
        return $ret;
    }    
}