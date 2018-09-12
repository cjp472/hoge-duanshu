<?php

namespace App\Http\Controllers\Admin\OpenPlatform\Publics;

use App\Http\Controllers\Admin\OpenPlatform\CoreTrait;
use GuzzleHttp\Client;

class MenuController extends PublicBaseController
{
    use CoreTrait;

    public function __construct()
    {
        parent::__construct();
    }

    public function create()
    {
        $method = 'POST';
        // 用户的actoken
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' 
            . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        $params = request('content');
        return (new Client)->request($url, $method, ['json' => $params])->getBody()->getContents();
    }

    public function get()
    {
        $method = 'GET';
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/get?access_token=' 
            . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        return (new Client)->request($url, $method)->getBody()->getContents();
    }
}
