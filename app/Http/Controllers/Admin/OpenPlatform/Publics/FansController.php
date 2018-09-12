<?php

namespace App\Http\Controllers\Admin\OpenOlatform\Publics;

use App\Http\Controllers\Admin\OpenPlatform\Publics\PublicBaseController;
use GuzzleHttp\Client;

class FansController extends PublicBaseController
{
    public function getFancList($next_openid = null)
    {
        $method = 'GET';
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/user/get?access_token=%s&next_openid=%s',
            $this->getAuthorizerAccessToken(), $next_openid);
        $res = (new Client)->request($method, $url)->getBody()->getContents();
        return $res;
    }
}
