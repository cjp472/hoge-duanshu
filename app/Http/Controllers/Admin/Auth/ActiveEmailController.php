<?php
namespace App\Http\Controllers\Admin\Auth;


use App\Http\Controllers\Admin\BaseController;
use App\Mail\UseRegister;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ActiveEmailController extends BaseController
{
    public function active($uid,$code)
    {
        $user = User::find($uid);
        if($cache_code = cache('email:active:'.$user->email)){
            if($code == $cache_code){
                Cache::forget('email:active:'.$user->email);
                $user->active = 1;
                $user->save();
                if(!Auth::guard()->check()){
                    Auth::guard()->loginUsingId($uid);
                    request()->session()->regenerate();
                }
                return redirect(config('app.url'));
            }
        }
        return view('emails.erroractive');
    }

    public function resendEmailCode($uid)
    {
        $user = User::find($uid);
        $code = md5($user->email.time());
        cache(['email:active:'.$user->email=> $code], EMAILCODE_EXPIRE);
        Mail::to($user->email)->send(new UseRegister($user,$code));
        return $this->output(['success'=>1]);
    }
}