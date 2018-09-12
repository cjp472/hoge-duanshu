<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 16/4/6
 * Time: 10:21
 */

namespace App\Http\Controllers\OauthService\Oauth2;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Shop;
use App\Models\UserShop;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;

class UserInfoController extends BaseController{

    /**
     * @return array
     * 获取店铺基本信息
     */
    public function getInfo(){
        $owner_id = Authorizer::getResourceOwnerId();
        $scopes = Authorizer::getScopes();
        if($scopes['baseinfo']->getId() != 'baseinfo'){
            $this->errorOutput('INVALID_SCOPE');
        }
        $shop = Shop::where('hashid',$owner_id)->first();
        $userShop = UserShop::where(['shop_id'=>$shop->hashid,'admin'=>1])->first();
        if($shop && $userShop){
            $user = $userShop->user;
            $data = [
                'openid'=>$shop->hashid,
                'title'=>$shop->title,
                'brief'=>$shop->brief,
                'account_id'    => trim($shop->account_id),
                'username' => $user->username?:'',
                'nickname' => $user->username?:'',
                'email'    => $user->email?:'',
                'mobile'   => $user->mobile?:'',
                'avatar'    => $user->avatar? : '',
                'source'    => 'duanshu',
            ];
            return $this->output($data);
        }
        return [];
    }
    /**
     * @return array
     * 获取店铺基本信息openid方式
     */
    public function getBaseInfo(){
        $this->validateWithAttribute([
            'openid'    => 'required|alpha_dash',
        ]);
        $scopes = Authorizer::getScopes();
        if($scopes['baseinfo']->getId() != 'baseinfo'){
            $this->errorOutput('INVALID_SCOPE');
        }
        $shop = Shop::where('hashid',request('openid'))->first();
        if($shop){
            $userShop = UserShop::where(['shop_id'=>$shop->hashid,'admin'=>1])->first();
            $user = $userShop->user;
            $data = [
                'openid'=>$shop->hashid,
                'title'=>$shop->title,
                'brief'=>$shop->brief,
                'account_id'    => trim($shop->account_id),
                'username' => $user->username?:'',
                'nickname' => $user->username?:'',
                'email'    => $user->email?:'',
                'mobile'   => $user->mobile?:'',
                'avatar'    => $user->avatar? : '',
                'source'    => 'duanshu',
            ];
            return $this->output($data);
        }
        $this->error('no-user');
    }
}
