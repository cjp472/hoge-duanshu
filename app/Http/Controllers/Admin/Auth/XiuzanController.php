<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/3/21
 * Time: 13:56
 */

namespace App\Http\Controllers\Admin\Auth;


use App\Http\Controllers\Admin\BaseController;
use App\Models\Shop;
use App\Models\UserShop;

class XiuzanController extends BaseController
{
    /**
     * 短书登录秀赞
     */
    public function xzLogin(){

        $this->validateWithAttribute([
            'mark'  => 'alpha_dash|max:20',
        ],[
            'mark'  => '应用标识',
        ]);
        $param = [
            'openid'=>$this->shop['id'],
            'redirect_to'   => request('mark') ? : 'feedback'
        ];

        $params = base64_encode(json_encode($param));

        $method = 'redirect';
        $ts = time();
        $data = 'method='.$method.'&ts='.$ts.'&params='.$params;
        $client_secret = config('define.xiuzan.param.client_secret');
        $signature = str_replace(['+', '/'], ['-', '_'], base64_encode(hash_hmac('sha1', $data, $client_secret, true)));
        $return = [
            'method'    => $method,
            'ts'        => $ts,
            'params'     => $params,
            'signature' => $signature,
        ];
        $url = config('define.xiuzan.api.xiuzan').http_build_query($return);

        return redirect($url);
//        return $this->output($url);

    }

}