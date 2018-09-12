<?php

namespace App\Http\Middleware;

use App\Models\UserShop;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class RedirectIfAuthenticated
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
        if (Auth::guard($guard)->check()) {
            $user_id = Auth::user()->id;
            $user = hg_user_response();
            $user['shop'] = hg_shop_response($user_id);
            return response(['response' => $user]);
        }

        return $next($request);
    }
}
