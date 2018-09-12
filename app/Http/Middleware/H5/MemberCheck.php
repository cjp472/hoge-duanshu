<?php
/**
 * 会员信息鉴权
 */
namespace App\Http\Middleware\H5;

use App\Models\Member;
use App\Models\PrivateSettings;
use App\Models\AppletSubmitAudit;
use App\Models\OpenPlatformApplet;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class MemberCheck
{
    public function handle($request, Closure $next)
    {
        $member = $request->header('x-member');
        if($request->token){
            switch (Route::currentRouteName()) {
                //小程序端
                case 'orderMake':
                case 'WXAppletSessionKey':
                case 'wxAppletLogin':
                    $member_str = '{"avatar":"https:\/\/wx.qlogo.cn\/mmopen\/vi_32\/CegvHbxjq9dSTzFOAkm3fEMcMSDZer8klMzmZQcOvAHvIPib1eKLb4EOuPuMVBibFbx23rIZMDXpezkUp72L78pQ\/0","expire":1520955043,"id":"50ce2209d00471b88f73b7592c010a78","nick_name":"桂","openid":"oRREf0athtpb_9R8gbtcZh7IVKIg","randomstr":"F3D2ERNrpxBv","secret":"b08fc641e99d4b9b82f868295f6e7144","source":"applet","timestamp":1520847043,"signature":"c52e1f3900fc6927d880fe56f023cb5bc28088c4"}';
                    break;
                default :
                    $member_str = '{"avatar":"http://thirdwx.qlogo.cn/mmopen/vi_32/IIlchyWEJQOBfFfl3R7GKoQS1Zm6j5IWlicGn17e5nTx5ZgjMW1nMb3nePt0A6BgNrKTlgkQ3LoTrDqWcHcXYLQ/132","expire":1520941246,"id":"670f7eb2572be66ec59743ad810559ff","nick_name":"桂","openid":"ow0MFxDn9MSGLtyg0FZbt4tVy_Q0","randomstr":"hNEj97Mn6PNv","secret":"b08fc641e99d4b9b82f868295f6e7144","source":"wechat","timestamp":1520833246,"signature":"552fb6deb76232bb18267b5b04931108c421c463"}';
                    break;
            }
            $member = urlencode($member_str);
        }
        $member && $sign = json_decode(rawurldecode($member),1);
        if(!$member || !$sign){
            return response([
                'error'     => 'no-member-info',
                'message'   => trans('validation.no-member-info'),
            ]);
        }
        if($sign){

            $donot_check = ['shareDetail'];
            $is_check = in_array(Route::currentRouteName(),$donot_check);
            $is_private = PrivateSettings::where('shop_id',$request->get('shop_id'))->value('is_private');
            /**
             * 检查当前版本小程序是否审核中
             **/
            $is_applet_audit = false;
            $version = $request->header('x-version');
            $platform = $request->header('x-platform');
            if($platform === 'applet' && $version) {
                $open_platform_applet = OpenPlatformApplet::where('shop_id', $request->get('shop_id'))->first();
                if ($open_platform_applet) {
                    $where = ['shop_id' => $request->get('shop_id'), 'appid' => $open_platform_applet->appid, 'applet_commit_id' => $version];
                    $submit_audit = AppletSubmitAudit::where($where)->orderBy('create_time', 'desc')->first();
                    $is_applet_audit = $submit_audit && $submit_audit->status === 2;
                }
            }
            //验证会员是否登录
            if(!$is_applet_audit && $is_private && Cache::get('member:status:'.$sign['id']) != $sign['signature'] && !$is_check) {
                return response([
                    'error' => 'member-no-login',
                    'message' => trans('validation.member-no-login'),
                ]);
            }

            if(!$is_applet_audit && $is_private && !$is_check && $sign['source'] != 'inner'){
                return response([
                    'error'     => 'shop-private',
                    'message'   => trans('validation.shop-private'),
                ]);
            }
            $isBlack = Redis::sismember($request->get('shop_id').':black:member', $sign['id']);
            if($isBlack){
                return response([
                    'error'     => 'member-black',
                    'message'   => trans('validation.member-black'),
                ]);
            }
            $data = json_decode(Cache::get('wechat:member:'.$sign['id']));
            if($data && ($data->shop_id != $request->get('shop_id'))){
                return response([
                        'error'     => 'member-shop-not-match',
                        'message'   => trans('validation.member-shop-not-match'),
                ]);
            }
            if (!$sign['id'])
                return response([
                    'error'     => 'member-no-login',
                    'message'   => trans('validation.member-no-login'),
                ]);
            if($sign['expire'] && time() > $sign['expire'])
            {
                return response([
                    'error'     => 'expire-signature',
                    'message'   => trans('validation.expire-signature'),
                ]);
            }
            $data = [
                'id'        => $sign['id'],
                'nick_name' => $sign['nick_name'],
                'openid'      => $sign['openid'],
                'source'    => $sign['source'],
                'avatar'    => $sign['avatar'],
            ];
            $data['timestamp'] = $sign['timestamp'];
            $data['randomstr'] = $sign['randomstr'];
            $data['secret'] = MEMBER_SECRET;
            $data['expire'] = $sign['expire'];
            ksort($data);
            $signurl = '';
            foreach($data as $k=>$v)
            {
                $signurl .= $k.'='.$v.'&';
            }
            $signurl = trim($signurl,'&');
            $signature = sha1($signurl);
            // if($sign['signature'] != $signature){
            //     return response([
            //         'error'     => 'error-signature',
            //         'message'   => trans('validation.error-signature'),
            //     ]);
            // }
        }
        $request->merge(['memberInfo'=>$sign]);
        //执行操作
        return $next($request);
    }
}