<?php
namespace App\Http\Controllers\Admin\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\Shop;
use App\Models\UserShop;
use App\Models\VersionExpire;
use App\Models\RegistTrack;
use App\Events\Registered;

class DynamicCodeController extends Controller
{
    public function login(Request $request)
    {
        $this->validateWith([
            'mobile' => 'required|numeric',
            'code'=>'required|numeric'
        ]);
        $input = $request->all();
        $mobile = $input['mobile'];
        if(!in_array($mobile, config('define.test_mobile'))){
            if($input['code'] != Cache::get('mobile:code:'.$input['mobile'])){
                $this->error('mobile_code_error');
            }
        }
        $user = User::where('mobile',$input['mobile'])->value('id');
        if($user){
            $this->guard()->loginUsingId($user);
            return $this->loginResponse();
        }else{
            $this->error('not_register');
        }
    }

    public function register(Request $request)
    {
        $this->validateWith([
            'mobile' => 'required|numeric',
            'code'=>'required|numeric',
            'search_word' => '',
            'search_engine' => ''
        ]);
        $input = $request->all();
        $mobile = $input['mobile'];
        if(in_array($mobile, config('define.test_mobile'))){
            $user = User::where('mobile',$input['mobile'])->first();
            if(!$user){
                event(new Registered($user = $this->create($input)));
            }
            $this->guard()->login($user);
        } else{
            if($input['code'] != Cache::get('mobile:code:'.$input['mobile'])){
                $this->error('mobile_code_error');
            }

            $user = User::where('mobile',$input['mobile'])->value('id');
            if($user){
                $this->error('already_register');
            }
            event(new Registered(
                $user = $this->create($input),
                $seo=['search_word'=> $request->input('search_word','未知'), 'search_engine'=> $request->input('search_engine', '未知')]
            ));
            $this->guard()->login($user);
        }
        return $this->registerResponse();
    }

    protected function guard()
    {
        return Auth::guard();
    }

    private function loginResponse()
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
        $obj = User::find(Auth::id());
        $obj->login_time = time();
        $obj->save();
        $user = hg_user_response();
        $user['shop'] = hg_shop_response(Auth::id());

        return $this->output($user);
    }

    private function create($input)
    {
        return User::create([
            'name'      => substr_replace($input['mobile'],'****',3,4),
            'username'  => $input['mobile'],
            'mobile'    => $input['mobile'],
            'password'  => '',
            'source'    => 'mobile',
            'active'    => USER_ACTIVE,
        ]);
    }

    private function registerResponse()
    {
        $user = hg_user_response();
        $user['shop'] = hg_shop_response(Auth::id());
        return $this->output($user);
    }
}
