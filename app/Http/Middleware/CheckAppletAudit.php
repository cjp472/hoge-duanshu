<?php
/**
 * Created by PhpStorm.
 * User: Janice
 * Date: 2018/1/3
 * Time: 下午4:11
 */

namespace App\Http\Middleware;

use Closure;
use App\Models\AppletSubmitAudit;
use App\Models\OpenPlatformApplet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CheckAppletAudit
{
    public function handle($request, Closure $next)
    {
        $shop = Session::get('shop:'.Auth::id());
        $shop_id = $shop['id'];
        $open_platform_applet = OpenPlatformApplet::where('shop_id',$shop_id)->first();
        if ($open_platform_applet) {
            $where = ['shop_id' => $shop_id, 'appid' => $open_platform_applet->appid];
            $submit_audit = AppletSubmitAudit::where($where)->orderBy('create_time', 'desc')->first();

            if($submit_audit && $submit_audit->status == 2){
                return response([
                    'error'     => 'shop-applet-audit',
                    'message'   => trans('validation.shop-applet-audit'),
                ]);
            }
        }
        return $next($request);
    }
}