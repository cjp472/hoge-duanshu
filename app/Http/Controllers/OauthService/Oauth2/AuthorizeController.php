<?php

namespace App\Http\Controllers\OauthService\Oauth2;

use App\Http\Controllers\Admin\BaseController;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;

class AuthorizeController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * get方法获取code
     */
    public function index()
    {
        $authParams = Authorizer::getAuthCodeRequestParams();
        $formParams = array_except($authParams,'client');
        $formParams['client_id'] = $authParams['client']->getId();
        $formParams['scope'] = implode(config('oauth2.scope_delimiter'), array_map(function ($scope) {
            return $scope->getId();
        }, $authParams['scopes']));
        $scopes = [];
        if($authParams['scopes'] && is_array($authParams['scopes'])){
            $scopes  = array_keys( $authParams['scopes']);
        }

        if($scopes && in_array('baseinfo',$scopes))
        {
            $params = Authorizer::getAuthCodeRequestParams();
            $params['shop_id'] = $this->shop['id'];
            // If the user has allowed the client to access its data, redirect back to the client with an auth code.
            $redirectUri = Authorizer::issueAuthCode('client', $params['shop_id'], $params);
            return redirect($redirectUri);
        }
        return view('oauth.authorization-form', ['params' => $formParams, 'client' => $authParams['client']]);
    }

    /**
     * @return mixed
     * post方法获取code
     */
    public function authLogin()
    {
        $params = Authorizer::getAuthCodeRequestParams();
        $params['shop_id'] = $this->shop['id'];
        $redirectUri = '/';
        // If the user has allowed the client to access its data, redirect back to the client with an auth code.
        if (request('approve')) {
            $redirectUri = Authorizer::issueAuthCode('client', $params['shop_id'], $params);
        }
        // If the user has denied the client to access its data, redirect back to the client with an error message.
        if (request('deny')) {
            $redirectUri = Authorizer::authCodeRequestDeniedRedirectUri();
        }
        return redirect($redirectUri);
    }
    /**
     * 获取access_token
     */
    public function issueToken()
    {
        return $this->output(Authorizer::issueAccessToken());
    }
    /**
     * 刷新access_token
     */
    public function refreshToken()
    {
        return $this->output(Authorizer::issueAccessToken());
    }
    /**
     * 判断access_token是否过期
     */
    public function verifyToken()
    {
        return $this->output(Authorizer::validateAccessToken());
    }
}
