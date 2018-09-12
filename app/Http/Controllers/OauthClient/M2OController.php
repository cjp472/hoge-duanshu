<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/11/21
 * Time: 09:27
 * m2o云授权
 */

namespace App\Http\Controllers\OauthClient;



use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserBind;
use App\Models\UserShop;
use App\Events\Registered;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class M2OController extends Controller
{
    use AuthenticatesUsers;

    protected $sign;
    protected $url;
    protected $openid;
    protected $_config;
    protected $state;

    /**
     * 嵌入m2o云登录
     */
    public function m2oCloudAuth(){

        $params = $this->cloud_validate();
        $url = $this->cloud_auth($params);
        if ($url)
            return redirect()->intended($this->url);
        return [];
    }

    /**
     * 处理m2o云数据
     * @return array|bool|mixed|string
     */
    private function cloud_validate(){
        $this->validateWithAttribute([
            'params' => 'required | max:1000 | min:3'
        ],[
            'params' => 'M2O云服务数据'
        ]);
        $this->sign = 'M2O';
        $this->_config = config('define.M2O');
        $this->url = config('define.M2O.redirect');

        $params = base64_decode(str_replace(['-', '_'], ['+', '/'], request('params')));
        $params = json_decode($params, 1);
        if ($params && is_array($params) && $params['openid']) {
            $this->openid = $params['openid'];
            return $params;
        }
        return [];
    }

    private function cloud_auth($params){
        $state = $params['extend_params'];
        if ($state) {
            $dep_union = json_decode($state,1);
            if ($dep_union && isset($dep_union['host_dep'])) {
                $this->state['host_dep'] = $dep_union['host_dep'] ?: 0;
                $sub_dep = explode(',', $dep_union['sub_dep']);
                $sub_dep[] = $dep_union['host_dep'];
                $this->state['sub_dep'] = $sub_dep;
            }
        }
        
        $bind_info = UserBind::where(['openid'=>$params['openid'],'source'=>$this->sign])->first();

        if ($bind_info && $bind_info->id) {
            if (!$bind_info->user_id) {
                return $this->update_user_info();
            } else {
                $user = User::find($bind_info->user_id);
                if ($this->client_cookie($user->toArray(),'M2O'))
                    return $this->url;
            }
        } else {
            return $this->insert_user_info();
        }
    }

    private function insert_user_info(){
        $info = $this->get_user_info();
        $info['m2o_bind']['bind_time'] = time();
        $info['m2o_bind']['user_id']   = $info['user_info']['id'];
        UserBind::create($info['m2o_bind']);

        if ($this->client_cookie($info['user_info'],'M2O'))
            return $this->url;
        return [];
    }

    private function get_user_info(){
        $m2o_client_token = $this->m2o_client_token();
        $m2o_client_info = $this->m2o_client_info($this->openid, $m2o_client_token);
        $m2o_bind = $this->m2o_bind($m2o_client_info);
        $register_info = $this->register_info($m2o_client_info);
        $user_info = $this->user_register($register_info);
        return ['m2o_bind'  => $m2o_bind, 'user_info' => $user_info];
    }

    /**
     * @return array
     * @throws \App\Exceptions\OutputExpection
     * 获取客户端access_token
     */
    private function m2o_client_token()
    {
        $param = $this->_config['param'];
        $param['grant_type'] = 'client_credentials';
        $param['scope'] = 'basic';
        $m2o_client_token = $this->curlClient($param,$this->_config['api']['token'],'POST');

        if (!$m2o_client_token)
            $this->error('token_error');
        if ($m2o_client_token && isset($m2o_client_token['error'])) {
            $this->errorWithText($m2o_client_token['error'], $m2o_client_token['error_description']);
        }
        return ['access_token' => $m2o_client_token['access_token']];
    }

    /**
     * @param $openid
     * @param $m2o_client_token
     * @return mixed
     * 获取客户端用户信息
     */
    private function m2o_client_info($openid, $m2o_client_token)
    {
        $m2o_client_info = $this->curlClient(['query'=>$m2o_client_token],$this->_config['api']['user'].'/'.$openid,'get');
        if (!$m2o_client_info) {
            $this->error('error_info');
        }
        if ($m2o_client_info && isset($m2o_client_info['error_message'])) {
            $this->errorWithText('error_info', $m2o_client_info['error_message']);
        }
        return $m2o_client_info['user'];
    }

    /**
     * @param array $info
     * @return array
     * m2o绑定信息
     */
    private function m2o_bind($info = [])
    {
        return [
            'nickname'    => trim($info['name']),
            'avatar'      => $info['avatar'] ? $info['avatar']['host'].$info['avatar']['dir'].$info['avatar']['filepath'].$info['avatar']['filename'] : '',
            'openid'      => $info['openid'],
            'unionid'     => $info['unionid'],
            'source'      => $this->sign,
            'create_time' => time(),
            'ip'          => hg_getip(),
            'agent'       => $_SERVER['HTTP_USER_AGENT'],
            'sex'         => 0,
        ];
    }

    /**
     * @param array $info
     * @return array
     * 注册信息处理
     */
    protected function register_info($info = [])
    {
        $data = [
            'nickname' => trim($info['name']),
            'avatar'   => $info['avatar'] ? $info['avatar']['host'].$info['avatar']['dir'].$info['avatar']['filepath'].$info['avatar']['filename'] : '',
            'source'   => strtolower($this->sign),
        ];
        return $data;
    }

    /**
     * @param array $info
     *
     * @return array
     * 用户注册流程
     */
    protected function user_register($info = [])
    {
        switch ($this->sign) {
            case 'M2O':
                $prefix = 'm2o';
                break;
            default :
                $prefix = 'ds';
                break;
        }
        $name = $this->get_name($prefix . '_', 10);
        $info['name'] = $name;
        $info['username'] = $name;
        $info['avatar'] && $info['avatar'] = '';//$this->client_avatar($info['avatar'], $info['name']);
        $info['ip'] = hg_getip();
        $info['active'] = 1;

        return $this->do_register($info,$prefix);
    }

    private function get_name($type,$num){
        $name = $type . str_random($num);
        if (User::where('username', $name)->value('id')) {
            $this->get_name($type,$num);
        }
        return $name;
    }

    /**
     * 注册信息处理
     * @param array $data
     * @param $prefix
     * @return $this|array|\Illuminate\Database\Eloquent\Model
     */
    protected function do_register($data = [],$prefix)
    {
        event(new Registered($user = User::create($data)));
        Auth::loginUsingId($user->id);

        $shop_id = UserShop::userShop(Auth::id())->shop_id;
        $userInfo = $this->registerRole($data,$prefix,$shop_id);

        Shop::where(['hashid'=>$shop_id])->update(['version'=>VERSION_ADVANCED]);

        Auth::loginUsingId($userInfo->id);
        request()->session()->regenerate();

        $user = hg_user_response();
        $user['shop'] = hg_shop_response(Auth::id());
        return $user;
    }

    /**
     * 注册角色账号信息，
     * @param $data
     * @param $prefix
     * @param $shop_id
     * @return $this|\Illuminate\Contracts\Auth\Authenticatable|\Illuminate\Database\Eloquent\Model|mixed|null|static
     */
    private function registerRole($data,$prefix,$shop_id)
    {
        if($this->state['host_dep']) {
            $userShopInfo = UserShop::where(['shop_id' => $shop_id, 'host_dep' => $this->state['host_dep']])->first();
            if (!$userShopInfo) {
                $name = $this->get_name($prefix . '_', 10);
                $data['name'] = $name;
                $data['username'] = $name;
                $data['create_user'] = Auth::id();
                $user = User::create($data);
                $userShop = new UserShop();
                $userShop->user_id = $user->id;
                $userShop->shop_id = $shop_id;
                $userShop->admin = 0;
                $userShop->effect = 1;
                $userShop->permission = serialize(array_keys(config('define.permission')));
                $userShop->host_dep = $this->state['host_dep'];
                $userShop->sub_dep = serialize($this->state['sub_dep']);
                $userShop->save();
                return $user;
            }
            $userShopInfo->sub_dep = serialize($this->state['sub_dep']);
            $userShopInfo->save();
            $user = User::find($userShopInfo->user_id);
            return $user;
        }
        return Auth::user();

    }

    /**
     * @param string $plat
     * @param array $info
     *
     * @return mixed
     * 将用户信息存储cookie
     */
    private function client_cookie($info = [], $plat = ''){

        Auth::loginUsingId($info['id']);

        $userInfo = $this->registerRole($info,'m2o',(UserShop::userShop(Auth::id()))->shop_id);

        $user = Auth::loginUsingId($userInfo->id);
        request()->session()->regenerate();
        $user->login_time = time();
        $user->save();

        $user = hg_user_response();
        $user['shop'] = hg_shop_response(Auth::id());

        $this->url = $this->url.'&userinfo='.json_encode($user);
        return $user;

    }

    /**
     * @return mixed
     * 修改绑定表用户信息
     */
    private function update_user_info()
    {
        $info = $this->get_user_info();
        UserBind::where(['openid' => $info['m2o_bind']['openid']])->update(['bind_time' => time(), 'user_id' => $info['user_info']['user_id']]);
        if ($this->client_cookie($info['user_info'],'M2O'))
            return $this->url;
        return [];
    }

}