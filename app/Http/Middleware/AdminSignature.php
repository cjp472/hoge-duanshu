<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/6/9
 * Time: 下午1:03
 */

namespace App\Http\Middleware;


use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Support\Facades\Session;

class AdminSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user_id = Auth::id();
        if (!$user_id ) {
            Session::flush();
            Session::regenerate();
            return response([
                'error'   => 'no-login',
                'message' => trans('validation.no-login'),
            ]);
        }
        $role = Auth::user()->roles;
        if( $role && count($role) > 0 ) {
            $user['role'] = $role[0]->name;
        }else{
            Session::flush();
            Session::regenerate();
            return response([
                'error'   => 'no-permission',
                'message' => trans('validation.no-permission',['attributes'=>'管理平台']),
            ]);
        }
        return $next($request);
    }
}
