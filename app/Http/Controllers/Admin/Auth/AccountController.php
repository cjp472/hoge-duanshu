<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Events\CurlLogsEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Shop;
use App\Models\ShopDisable;
use App\Models\ShopFunds;
use App\Models\ShopScore;
use App\Models\User;
use App\Models\UserShop;
use App\Models\VersionExpire;
use App\Models\VersionOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;


class AccountController extends BaseController{

    /**
     * @return mixed
     * 账号/子账号详情
     */
    public function accountDetail(){
        $user_id = $this->user['id'];
        $user = User::findOrFail($user_id);
        $shop = UserShop::userShop($user_id);
        $response = [
            'id'        => $user->id,
            'username'  => $user->username,
            'password'  => $user->password ? true : false,
            'admin'     => $shop->admin?1:0,
            'mobile'    => trim($user->mobile),
            'avatar'    => trim($user->avatar),
            'source'    => trim($user->source),
            'active'    => intval($user->active),
            'verify_status' => $shop->verify_status,
            'verify_first_type'=> $shop->verify_first_type,
            'account_id'=> trim($shop->account_id),
            'money' => $this->getMoney(),
            'withdraw_account'  => trim($shop->withdraw_account),
            'balance' => ShopFunds::getBalance($this->shop['id']),
            'is_bug_version' => $this->checkBuyVersion()?1:0,
            'closed' => ShopDisable::isShopDisable($this->shop['id']),
        ];
        return $this->output($response);
    }

    /**
     * 获取当前登录用户信息和店铺信息
     */
    public function info(){
        $user = $this->user;
        $user_id = $user ? $user['id'] : '';
        //判断角色账号是否失效
        if(!UserShop::where('user_id', $user_id)->value('effect')){
            $this->guard()->logout();
            $this->error('account_failure');
        }
        if(UserShop::userShop($user_id)->is_black){
            $this->guard()->logout();
            $this->error('shop-locked');
        }
        $user['shop'] = hg_shop_response($user_id);

        return $this->output($user);
    }

    private function getShopScore()
    {
        $client = $this->initClient();//生成签名
        $url = config('define.service_store.api.shop_score');
        $res = $client->request('get', $url);
        $data = json_decode($res->getBody()->getContents());
        if ($res->getStatusCode() !== 200) {
            $score = 0;
        } elseif ($res && $data->error_code) {
            $score = 0;
        } else {
            $score = $data->result->token;
        }
        return sprintf("%.2f", $score);
    }

//    private function checkShopClosed(){
//        $close_shops = Redis::smembers('close:shop');
//        if(in_array($this->shop['id'],$close_shops)){ //店铺打烊
//            return 1;
//        }else{
//            return 0;
//        }
//    }

    //检测是否购买了认证以及认证是否过期
    private function checkBuyVersion(){
        $shop = Shop::where('hashid',$this->shop['id'])->first();
        if($shop->version !=VERSION_BASIC){
            return 1;
        }else{
            if($shop->version==VERSION_BASIC && $shop->verify_expire > time()){
                return 1;
            }else{
                return 0;
            }
        }
    }

    /**
     * 获取提现金额
     */
    private function getMoney(){
        $client = $this->initClient(); //初始化 client
        $url = config('define.order_center.api.withdraw_money');
        $mon = 1;
        try {
            $res = $client->request('GET',$url);
        }catch (\Exception $exception){
            $mon = 0;
            $result = $exception->getMessage();
            event(new CurlLogsEvent($result,$client,$url));
        }
        if($mon) {
            $result = json_decode($res->getBody()->getContents());; //出错处理和接收数据
            $mon = isset($result->result->available) ? $result->result->available/100 : 0;
            event(new CurlLogsEvent(json_encode($result),$client,$url));
        }
        return sprintf('%.2f',$mon);
    }


    /**
     * 判断手机号是否已经注册
     * @return mixed
     */
    public function checkMobile()
    {
        $this->validateWithAttribute([
            'mobile' => 'required|regex:/^1[3,4,5,7,8,9]\d{9}$/'
        ], [
            'mobile' => '手机号',
        ]);
        $exists = User::where('mobile', request('mobile'))->value('id');
        return $this->output(['is_exists' => $exists ? 1 : 0]);
    }
    /**
     * @return mixed
     * 账号设置
     */
    public function accountSet(){
        $this->validateAccount();
        $this->setAccountData();
        return $this->output(['success'=>1]);
    }

    private function setAccountData(){
        $user = User::findOrFail($this->user['id']);
        if(request('type')=='mobile'){
            $user->mobile = request('mobile');
            $user->password = bcrypt(request('password'));
            $user->saveOrFail();
        }elseif (request('type')=='email'){
            $user->email = request('email');
            $user->password = bcrypt(request('password'));
            $user->saveOrFail();
        }
    }

    private function validateAccount()
    {
        $this->validateWithAttribute(['type' => 'required'], ['type' => '账号类型']);
        switch (request('type')) {
            case 'email':
                $this->validateWithAttribute([
                    'email' => 'required|email|max:255|unique:users',
                    'password' => 'required|alpha_num|min:6|confirmed',
                ], [
                    'email' => '邮箱',
                    'password' => '密码',
                ]);
                break;
            case 'mobile' :
                $this->validateWithAttribute([
                    'mobile' => 'required|max:11|unique:users',
                    'code' => 'required|numeric',
                    'password' => 'required|alpha_num|min:6|confirmed',
                ], [
                    'mobile' => '手机',
                    'code' => '验证码',
                    'password' => '密码',
                ]);
                $code = Cache::get('verifyCode:' . request('mobile'));
                if ($code != request('code')) {
                    return $this->error('error_code');
                }
                break;
        }
    }

    /**
     * 手机号绑定
     */
    public function mobileBind(){
        $this->validateWithAttribute([
            'mobile'=>'required|max:11',
            'code'  =>'required|numeric',
        ], [
            'mobile'=>'手机号',
            'code'  =>'验证码',
        ]);
        if(request('code') != Cache::get('mobile:code:'.request('mobile'))){
            $this->error('mobile_code_error');
        }
//        if(!Hash::check(request('password'),User::where('id',$this->user['id'])->value('password'))){
//            $this->error('password_error');
//        }
        $user = User::findOrFail($this->user['id']);
        $user->mobile = request('mobile');
        $user->saveOrFail();
        return $this->output(['success'=>1]);
    }

    /**
     * 短信验证码验证
     */
    public function verifyCode(){
        $this->validateWithAttribute([
            'mobile'=>'required|max:11',
            'code'  =>'required|numeric',
        ], [
            'mobile'=>'手机号',
            'code'  =>'验证码',
        ]);
        if(request('code') != Cache::get('mobile:code:'.request('mobile'))){
            $this->error('mobile_code_error');
        }else{
            return $this->output(['success'=>1]);
        }
    }
    private function initClient()
    {
        $appId = config('define.order_center.app_id');
        $appSecret = config('define.order_center.app_secret');
        $timesTamp = time();
        $client = hg_verify_signature([],$timesTamp,$appId,$appSecret,$this->shop['id']);
        return $client;
    }


    /**
     * Reset the given user's password.
     * @param Request $request
     * @return mixed
     */
    public function updatePassword(Request $request)
    {   
        $user = User::find($this->user['id']);
        if (!$user) {
            return Password::INVALID_USER;
        }
        if ($user->password == '' && $user->mobile) {
            $rules = [
            'password' => 'required|min:6',
            'code' => '',
            'mobile' => 'required'
            ];
            $checkoutCode = false;
        } else {
            $rules = [
            'password' => 'required|min:6',
            'code' => 'required',
            'mobile' => 'required'
            ];
            $checkoutCode = true;
        }
        
        $this->validateWithAttribute($rules,[
            'password' => '新密码',
            'code' => '验证码',
            'mobile' => '手机号'
        ]);
        $credentials = $this->Credentials($request);
        $response = $this->validateUpdate($credentials, $checkoutCode, $user);

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $response == Password::PASSWORD_RESET
            ? $this->sendResetResponse($response)
            : $this->sendResetFailedResponse($request, $response);


    }

    /**
     * Get the password reset credentials from the request.
     * @param Request $request
     * @return array
     */
    private function Credentials(Request $request)
    {
        return $request->only(
            'code', 'password','mobile'
        );
    }

    private function validateUpdate($credentials, $checkoutCode, $user){
//        if (! $this->validateOldPassword($user,$credentials)) {
//            return 'passwords.old_password';
//        }
//        if (! $this->validateNewPassword($credentials)) {
//            return Password::INVALID_PASSWORD;
//        }
        if ($credentials['mobile'] != $user->mobile) {
            $this->error('mobile_not_the_same_error');
        }
        if($credentials['code'] && $credentials['code'] != Cache::get('mobile:code:'.$credentials['mobile'])){
            $this->error('mobile_code_error');
        }
        $user->forceFill([
            'password' => bcrypt($credentials['password']),
            'remember_token' => Str::random(60),
        ])->save();
        return Password::PASSWORD_RESET;
    }

    private function validateOldPassword($user,$credentials){
        if(Hash::check($credentials['old_password'],$user->password)){
            return true;
        }
        return false;

    }

    private function validateNewPassword($credentials){
        list($password, $confirm) = [
            $credentials['password'],
            $credentials['password_confirmation'],
        ];

        return $password === $confirm && mb_strlen($password) >= 6;
    }

    /**
     * Get the response for a successful password reset.
     *
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse
     */
    private function sendResetResponse($response)
    {
        return $this->output([
            'success' => 1
        ]);
    }

    /**
     * Get the response for a failed password reset.
     *
     * @param  \Illuminate\Http\Request
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse
     */
    private function sendResetFailedResponse(Request $request, $response)
    {
        return response()->json([
            'error'     => 'password_reset_error',
            'message'   => trans($response),
        ]);

    }

}