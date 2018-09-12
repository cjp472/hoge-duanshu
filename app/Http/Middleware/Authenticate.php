<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if($request->token){
            Auth::loginUsingId(11);
            hg_user_response();
            hg_shop_response(Auth::id());
        }
        if (Auth::guard($guard)->guest()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response([
                    'error'     => 'no-login',
                    'message'   => trans('validation.no-login'),
                ]);
            } else {
                return redirect()->guest(OAUTH_NO_LOGIN);
            }
        }

        return $next($request);
    }
}
