<?php
/**
 * admin端的基类
 */
namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class BaseController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::id() ? [
                'id'    => Auth::id(),
                'name'  => Auth::user()->name,
            ] : [];
            return $next($request);
        });
    }
}