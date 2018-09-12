<?php

namespace App\Console\Commands;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class WeappSetLowestVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weapp:set:lowest:version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '批量设置小程序最低版本号';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
//        $open_platforms = OpenPlatformApplet::select(['appid','access_token','refresh_token', 'old_refresh_token'])
//            ->get();
//        foreach ($open_platforms as $open_platform) {
//            try {
//                echo 'appid:'.$open_platform->appid."==========================\n";
//                $weapp_now_version = $this->getWeappNowVersion($open_platform);
//                echo '$weapp_now_version before:'.$weapp_now_version."\n";
//                if($weapp_now_version < WEAPP_SUPPORT_LOWEST_VERSION){
//                    $this->setWeappNowVersion($open_platform, WEAPP_SUPPORT_LOWEST_VERSION);
//                    $weapp_now_version = $this->getWeappNowVersion($open_platform);
//                    echo '$weapp_now_version after:'.$weapp_now_version."\n";
//                }
//            }catch (\Exception $e){
//
//            }
//        }
    }

    private function setWeappNowVersion($open_platform, $version){
        $access_token = $this->getAuthorizerAccessToken($open_platform)['authorizer_access_token'];
        $url = config('define.open_platform.wx_applet.api.setweappsupportversion')
            . '?access_token=' . $access_token;
        $params = ['version' => $version];
        $res = $this->curl_trait('POST', $url, $params);
        return $res;
    }

    private function getWeappNowVersion($open_platform){
        $now_version = '';
        $access_token = $this->getAuthorizerAccessToken($open_platform)['authorizer_access_token'];
        $url = config('define.open_platform.wx_applet.api.getweappsupportversion')
            . '?access_token=' . $access_token;
        $res = $this->curl_trait('POST', $url);
        if($res && isset($res['now_version'])){
            $now_version = $res['now_version'];
        }
        return $now_version;
    }

    private function curl_trait($method, $url, $params = '', $headers = '', $body = '', $refresh_token = false)
    {
        try {
            if ($headers || $body) {
                $client = new Client([
                    'headers' => $headers,
                    'body'    => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);
            } else {
                $client = new Client([]);
            }
            $params = $params ?: [];
            if ($method != 'GET') {
                $params = ['json' => $params];
            }
            $response = $client->request($method, $url, $params);
            $response = $response->getBody()->getContents();
            event(new CurlLogsEvent($response,$client,$url));
        } catch (\Exception $e) {
            event(new ErrorHandle($e));
            $this->error('curl_trait_fail');
        }
        if ($response = json_decode($response, 1)) {
            if (isset($response['errmsg']) && ($response['errmsg'] != 'ok')) {
                if ($refresh_token) {
                    return false;
                } else {
                    $errmsg = config('define.open_platform.wx_applet.validation')[$response['errcode']] ?? $response['errmsg'];
                    echo $errmsg;
                }
            } else {
                return $response;
            }
        }
    }

    //获取第三方平台token
    private function getComponentAccessToken()
    {
        $component_verify_ticket = Redis::get($this->keyName('component_verify_ticket'));
        if (!$component_verify_ticket) {
            $this->error('no_component_verify_ticket');
            return;
        }
        $url = config('define.open_platform.wx_applet.api.api_component_token');
        $params = [
            'component_appid'         => config('wechat.open_platform.app_id'),
            'component_appsecret'     => config('wechat.open_platform.secret'),
            'component_verify_ticket' => $component_verify_ticket,
        ];
        echo "getComponentAccessToken\n";
        $response = $this->curl_trait('POST', $url, $params);
        if ($response['component_access_token']) {
            Redis::setex($this->keyName('component_access_token'),
                $response['expires_in'],
                $response['component_access_token']);
            return $response['component_access_token'];
        }
    }

    //获取授权方token
    private function getAuthorizerAccessToken($open_platform)
    {
        $authorizationData = [];
        if (!$open_platform) {
            return;
        }
        $access_token = Redis::get($this->keyName('authorizer_access_token') . $open_platform->appid);
        if (!$access_token) {
            $component_access_token = Redis::get($this->keyName('component_access_token'));
            if (!$component_access_token) {
                $component_access_token = $this->getComponentAccessToken();
            }
            $url = config('define.open_platform.wx_applet.api.api_authorizer_token')
                . '?component_access_token=' . $component_access_token;
            $params = [
                'component_appid'          => config('wechat.open_platform.app_id'),
                'authorizer_appid'         => $open_platform->appid,
                'authorizer_refresh_token' => $open_platform->old_refresh_token
            ];
            $authorizationInfo = $this->curl_trait('POST', $url, $params, '', '', true);
            if (!$authorizationInfo) {
                $params['authorizer_refresh_token'] = $open_platform->refresh_token;
                $authorizationInfo = $this->curl_trait('POST', $url, $params);
            }
            Redis::setex($this->keyName('authorizer_access_token') . $open_platform->appid,
                $authorizationInfo['expires_in'],
                $authorizationInfo['authorizer_access_token']
            );
            $open_platform->access_token = $authorizationInfo['authorizer_access_token'];
            $open_platform->old_refresh_token = $params['authorizer_refresh_token'];
            $open_platform->refresh_token = $authorizationInfo['authorizer_refresh_token'];
            $open_platform->update_time = time();
            $open_platform->save();
        }
        $authorizationData['authorizer_access_token'] = $access_token ?: $authorizationInfo['authorizer_access_token'];
        $authorizationData['open_platform'] = $open_platform;
        return $authorizationData;
    }

    private function keyName($key)
    {
        $main_key = 'openflatform:duanshu:';
        $config = [
            'authorizer_access_token' => $main_key . 'authorizeraccesstoken:',
            'component_access_token'  => $main_key . 'componentaccesstoken',
            'component_verify_ticket' => $main_key . 'componentverifyticket',
        ];
        return $config[$key];
    }
}
