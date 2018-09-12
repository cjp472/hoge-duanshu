<?php
namespace App\Http\Controllers\Admin\Auth;

use App\Events\Registered;
use App\Mail\UseRegister;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */
    use RegistersUsers;
    /**
     * Where to redirect users after registration.
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
        $this->middleware('guest');
    }
    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'username' => 'required|alpha_num|max:32|unique:users',
            'password' => 'required|alpha_num|min:6|confirmed',
            'agree'     => 'required|numeric|size:1',
        ]);
    }
    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['username'],
            'username' => $data['username'],
            'password' => bcrypt($data['password']),
            'source' => 'duanshu',
            'active' => USER_ACTIVE,
        ]);
    }

    /**
     * The user has been registered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function registered(Request $request, $user)
    {
        $user = hg_user_response();
        $user['shop'] = hg_shop_response(Auth::id());
        return $this->output($user);
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        event(new Registered($user = $this->create($request->all())));

        $this->guard()->login($user);

        return $this->registered($request, $user)
            ?: redirect($this->redirectPath());
    }
}

/**
 * 邮箱注册
 * Class EmailRegisterController
 * @package App\Http\Controllers\Admin\Auth
 */
class EmailRegisterController extends RegisterController
{
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|alpha_num|min:6|confirmed',
            'agree'     => 'required|numeric|size:1',
        ]);
    }

    protected function create(array $data)
    {
        if(!isset($data['name']) && isset($data['email'])){
            $data['name'] = $data['email'];
        }
        return User::create([
            'name'      => $data['name'],
            'username'  => $data['email'],
            'email'     => $data['email'],
            'password'  => bcrypt($data['password']),
            'source'    => 'email',
            'active'    => USER_ACTIVE,
        ]);
    }

    protected function registered(Request $request, $user)
    {
        $code = md5($request->email.time());
        cache(['email:active:'.$request->email=> $code], EMAILCODE_EXPIRE);
        Mail::to($request->email)->send(new UseRegister(Auth::user(),$code));
        
        $user = hg_user_response();
        $user['shop'] = hg_shop_response(Auth::id());
        return $this->output($user);
    }
}

/**
 * 手机注册
 * Class MobileRegisterController
 * @package App\Http\Controllers\Admin\Auth
 */
class MobileRegisterController extends RegisterController
{
    protected function validator(array $data)
    {
        if(!isset($data['code'])){
            $this->error('no_mobile_code');
        }
        if($data['code'] != Cache::get('mobile:code:'.(isset($data['mobile'])?$data['mobile']:''))){
            $this->error('mobile_code_error');
        }
        return Validator::make($data, [
            'mobile' => 'required|max:11|unique:users',
            'password' => 'required|alpha_num|min:6|confirmed',
            'agree'     => 'required|numeric|size:1',
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'name'      => substr_replace($data['mobile'],'****',3,4),
            'username'  => $data['mobile'],
            'mobile'    => $data['mobile'],
            'password'  => bcrypt($data['password']),
            'source'    => 'mobile',
            'active'    => USER_ACTIVE,
        ]);
    }
}