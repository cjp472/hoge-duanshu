<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

class ManagePermission
{
    protected $dontCheckRoute = [];

    public function handle($request, Closure $next)
    {
        $shop = Auth::id() ? Session::get('shop:'.Auth::id()) : [];
        if(!$shop){
            return response([
                'error'     => 'no-login',
                'message'   => trans('validation.no-login'),
            ]);
        }
        if(getenv('APP_DEBUG') == 'true'){
            return $next($request);
        }
        if(!$this->dontCheckRoute()){
            $sign = explode('/',$request->getPathInfo());
            $mark = $sign[2];
            switch ($sign[2]){
                case 'shop':
                    if($sign[3] == 'update'){
                        $mark = 'shop_update';
                    }
                    if($sign[3] == 'black'){
                        $mark = 'black';
                    }
                    break;
                default:
                    $mark = $sign[2];
                    break;
            }
            if($mark && !in_array(Auth::id(),config('define.admin_super_id')) && in_array($mark,config('define.admin_permission_except'))){
                return response([
                    'error'     => 'no-permission',
                    'message'   => trans('validation.no-permission',[
                        'attributes'   => config('define.permission.'.$mark)
                    ]),
                ]);
            }
        }
        $response = $next($request);
        return $response;
    }

    private function dontCheckRoute()
    {
        return in_array(Route::currentRouteName(),$this->dontCheckRoute) ? 1 : 0;
    }
}