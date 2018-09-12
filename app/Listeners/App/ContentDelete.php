<?php

namespace App\Listeners\App;


use App\Events\AppEvent\AppContentDeleteEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\ShopApp;


class ContentDelete
{

    public function handle(AppContentDeleteEvent $event)
    {

        $shop_app = ShopApp::where('shop_id', $event->data['shop_id'])->first();
        if ($shop_app && $shop_app->appkey && $shop_app->appsecret) {
            $param = ['model' => unserialize($shop_app->model_slug)[$event->data['type']], 'data' => [$event->data['content_id']]];
            $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.contentsDelete'));

            $client = hg_hash_sha1($param, $shop_app->appkey, $shop_app->appsecret, time());
            try {
                $response = $client->request('POST', $url);
            } catch (\Exception $exception) {
                event(new ErrorHandle($exception, 'app'));
                return false;
            }

            $result = json_decode($response->getBody()->getContents(), 1);

            event(new CurlLogsEvent(json_encode($result), $client, str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.contentsDelete'))));
        }
    }
}
