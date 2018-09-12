<?php
namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBind;
use Doctrine\Common\Cache\PredisCache;
use EasyWeChat\Foundation\Application;
use App\Events\Registered;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Overtrue\Socialite\AuthorizeFailedException;

class WechatController extends Controller
{
    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * 微信登录
     * @return mixed
     */
    public function login()
    {
        $user = $this->getWechatUser();
        $userBind = $this->checkUserBind($user);
        if($userBind && $userBind->user_id){
            $this->doLogin($userBind->user_id);
        }else{
            $this->doRegister($user);
            $this->bindUser($userBind);
        }
        return $this->output($this->response());
    }

    /**
     * 微信绑定
     * @return mixed
     */
    public function bind()
    {
        $user = $this->getWechatUser();
        $userBind = $this->checkUserBind($user); //检测微信是否已被绑定
        if($userBind && $userBind->user_id){
            //微信已被绑定
            $this->error('wechat_already_bind');
        }
        if($this->checkBindUser(Auth::id())){ //检测用户是否已被绑定
            //用户已绑定过微信
            $this->error('user_already_bind');
        }
        $this->bindUser($userBind);
        return $this->output(['success'=>1]);
    }

    /**
     * 获取微信用户信息
     * @return $this|\Overtrue\Socialite\User
     */
    private function getWechatUser()
    {
        $options = config('wechat.open_platform');
        $app = new Application($options);
        $app->cache = new PredisCache(app('redis')->connection()->client());
        $oauth = $app->oauth;
        try{
            $user = $oauth->user();
        }
        catch(AuthorizeFailedException $e){
            throw new HttpResponseException($this->errorWithText('wechat_error_'.$e->body['errcode'],$e->body['errmsg']));
        }
        return $user;
    }

    /**
     * 判断微信是否已经绑定了用户
     * @param $user
     * @return UserBind
     */
    private function checkUserBind($user)
    {
        $userBind = UserBind::where([
            'openid'    => $user->getId(),
            'source'    => 'wechat',
        ])->first();
        if(!$userBind){
            $userBind = new UserBind();
            $userBind->openid = $user->getId();
            $userBind->source = 'wechat';
            $userBind->nickname = $user->getName();
            $userBind->avatar = $user->getAvatar();
            $orginal = $user->getOriginal();
            $userBind->sex = intval($orginal['sex']);
            $userBind->unionid = $orginal['unionid'] ;
            $userBind->user_id = 0;
            $userBind->create_time = time();
            $userBind->ip = hg_getip();
            $userBind->save();
        }
        return $userBind;
    }

    /**
     * 检测用户是否已经绑定了微信
     * @param $user_id
     * @return int
     */
    private function checkBindUser($user_id){
        $user_bind = UserBind::where('user_id',$user_id)->first();
        return $user_bind ? 1 : 0;
    }

    /**
     * 绑定微信
     * @param UserBind $userBind
     */
    private function bindUser(UserBind $userBind)
    {
        $userBind->bind_time = time();
        $userBind->user_id = Auth::id();
        $userBind->save();
    }

    /**
     * 未注册过的微信用户进行注册
     * @param $user
     */
    private function doRegister($user)
    {
        event(new Registered($user = $this->create($user)));
        Auth::guard()->login($user,true);
    }

    /**
     * 微信用户登录
     * @param $user_id
     */
    private function doLogin($user_id)
    {
        Auth::guard()->loginUsingId($user_id);
        request()->session()->regenerate();
    }

    /**
     * 创建一个用户
     * @param $data
     * @return mixed
     */
    protected function create($data)
    {
        return User::create([
            'name' => $data->getName(),
            'avatar' => $data->getAvatar(),
            'source' => 'wechat',
        ]);
    }

    /**
     * The user has been registered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function response()
    {
        $user = hg_user_response();
        $user['shop'] = hg_shop_response(Auth::id());
        return $user;
    }
}