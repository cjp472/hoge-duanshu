<?php
namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserShop;
use App\Models\VersionExpire;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */
    use AuthenticatesUsers;
    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateLogin(Request $request)
    {
        //监控API接口参数处理
        if($request->input('token')){
            $request->replace([
                'login_name'    => 'duanshu@hoge.cn',
                'password'      => '123456',
            ]);
        }

        //客户端时不要图片验证码
        if(!$request->header('x-device-id') && Cache::has('login_count_'.trim($request->input('login_name'))) && intval(Cache::get('login_count_'.trim($request->input('login_name')))) >= 3){
            if(!request('captcha') || !$this->checkCaptcha(request('captcha'))){
                $this->error('captcha-error');
            }
        }

        if(!User::where($this->username(),trim($request->input('login_name')))->value('id')){

            if(Cache::has('login_count_'.trim($request->input('login_name')))){
                Cache::increment('login_count_'.trim($request->input('login_name')));
            }else{
                Cache::put('login_count_'.trim($request->input('login_name')),1,5);
            }
            //$this->error('not_register');
            $response = new Response([
                'error'     => 'not_register',
                'message'   => trans('validation.not_register'),
                'count' => intval(Cache::get('login_count_'.trim($request->input('login_name'))))
            ], 200);
            throw new HttpResponseException($response);
        }
        $request->merge([$this->username() => trim($request->input('login_name'))]);
        return $this->validateWith([
            $this->username() => 'required|string',
            'password' => 'required|alpha_num|min:6',
        ]);
    }

    /**
     * 登录成功 json 输出
     * @param $request
     * @param $user
     * @return mixed
     */
    protected function authenticated($request, $user)
    {

        //判断角色账号是否失效
        if(!UserShop::where('user_id',Auth::id())->value('effect')){
            $this->guard()->logout();
            $this->error('account_failure');
        }
        if(UserShop::userShop(Auth::id())->is_black){
            $this->guard()->logout();
            $this->error('shop-locked');
        }
        $user->login_time = time();
        $user->save();
        $user = hg_user_response();
        $user['shop'] = hg_shop_response(Auth::id());
        if($request->manage){
            $role = Auth::user()->roles;
            if( $role && count($role) > 0 ) {
                $user['role'] = $role[0]->name;
            }else{
                $this->guard()->logout();
                $this->error('no-permission',['attributes'=>'管理平台']);
            }
        }

        if($user['shop']['version'] == VERSION_BASIC){
            $version_expire = VersionExpire::where(['hashid' => $user['shop']['id'], 'version' => VERSION_BASIC, 'is_expire'=>0])->first();
            if($version_expire && strtotime('+7 day',$version_expire->start) == $version_expire->expire){
                $user['shop']['shop_state'] = 'probation'; //店铺状态--试用期
            }
        }

        Cache::forget('login_count_'.trim($request->input('login_name')));

        return $this->output($user);
    }

    /**
     * 重写登录失败报错json 输出
     *
     * @param \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        if(Cache::has('login_count_'.trim($request->input('login_name')))){
            Cache::increment('login_count_'.trim($request->input('login_name')));
        }else{
            Cache::put('login_count_'.trim($request->input('login_name')),1,5);
        }
        $errors = [$this->username() => trans('auth.failed')];

        if ($request->expectsJson()) {
            return response()->json([
                'error'     => $this->username(),
                'message'   => trans('auth.failed'),
                'count' => intval(Cache::get('login_count_'.trim($request->input('login_name'))))
            ]);
        }

        return redirect()->back()
            ->withInput($request->only($this->username(), 'remember'))
            ->withErrors($errors);
    }


    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->flush();

        $request->session()->regenerate();

        return $this->output([
            'success'   => true,
        ]);
    }

    public function username()
    {
        if(hg_check_is_mobile(request('login_name'))){
            return 'mobile';
        }
        if(hg_check_is_email(request('login_name'))){
            return 'email';
        }
        return 'username';
    }

    protected function checkCaptcha($captcha)
    {
        $cacheCaptcha = Redis::lrange('captcha',0,-1);
        $captcha = strtolower($captcha);
        if(in_array($captcha,$cacheCaptcha)){
            Redis::lrem('captcha',0,$captcha);
            return true;
        }
        return false;
    }

    /**
     * Redirect the user after determining they are locked out.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendLockoutResponse(Request $request)
    {
        $seconds = $this->limiter()->availableIn(
            $this->throttleKey($request)
        );

        $message = trans('auth.throttle', ['seconds' => $seconds]);

        $errors = [$this->username() => $message];

        if ($request->expectsJson()) {
            return response([
                'error'   => 'Locked',
                'message' => $message,
            ]);
        }

        return redirect()->back()
            ->withInput($request->only($this->username(), 'remember'))
            ->withErrors($errors);
    }
}
