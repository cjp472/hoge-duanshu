<?php

namespace App\Http\Controllers\Admin\OpenPlatform\Publics;

use App\Events\CurlLogsEvent;
use App\Http\Controllers\Admin\OpenPlatform\CoreTrait;
use App\Http\Controllers\Admin\OpenPlatform\Publics\PublicBaseController;
use GuzzleHttp\Client;

class MaterialController extends PublicBaseController
{
    use CoreTrait;

    public function __construct($shop_id = null)
    {
        parent::__construct();
        if ($shop_id) $this->shop['id'] = $shop_id; 
    }
    /**
     * 获取素材列表
     *
     * @param  string  $app_id 授权方app_id
     * @param  string  $type   素材的类型，图片（image）、视频（video）、语音 （voice）、图文（news）
     * @param  integer $offset 从全部素材的该偏移位置开始返回，0表示从第一个素材 返回
     * @param  integer $count  返回素材的数量，取值在1到20之间
     *
     * @return json
     */
    public function getMaterialList($app_id = '', $type = null, $offset = 0, $count = 20)
    {
        $url = config('define.open_platform.public.api.batchget_material')
            . '?access_token=' . $this->getAuthorizerAccessToken($app_id)['authorizer_access_token'];
        $params = compact('type', 'offset', 'count');
        $client = new Client([]);
        $json = $client->request('POST', $url, ['json' => $params])->getBody()->getContents();
        $json = preg_replace('/[\x00-\x1F\x80-\x9F]/u', '', trim($json, chr(239).chr(187).chr(191)));
//        $json = iconv('GBK', 'utf-8', $json);
//        $json = stripslashes($json);
//        $json = htmlspecialchars_decode($json);
        $response = json_decode($json, 1);
        event(new CurlLogsEvent(json_encode($response),$client,$url));

        return $response;
//        return $this->curl_trait('POST', $url, $params);
    }

    /**
     * 获取永久素材
     *
     * @param  string $media_id 要获取的素材的media_id
     *
     * @return json
     */
    public function getMaterialDetail($type, $media_id)
    {
        $url = config('define.open_platform.public.api.get_material')
            . '?access_token=' . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        return ('news' === $type || 'video' === $type)
            ? $this->curl_trait('POST', $url, ['media_id' => $media_id])
            : $url;
    }

    /**
     * 获取素材总数
     * @param string $app_id
     * @return mixed
     */
    public function getMaterialCount($app_id = ''){

        $url = config('define.open_platform.public.api.get_materialcount')
            . '?access_token=' . $this->getAuthorizerAccessToken($app_id)['authorizer_access_token'];
        $response = $this->curl_trait('GET', $url);
        return $response;
    }
}
