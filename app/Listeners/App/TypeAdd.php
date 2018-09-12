<?php

namespace App\Listeners\App;

use App\Events\AppEvent\AppTypeAddEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\FailContentSyn;
use App\Models\ShopApp;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class TypeAdd
{
    /**
     * Handle the event.
     *
     * @param  AppTypeAddEvent  $event
     * @return void
     */
    public function handle(AppTypeAddEvent $event)
    {
        $shop_app = ShopApp::where('shop_id',$event->shop_id)->first();
        if($shop_app) {
            $data['data'] = $event->data;
            $data['model'] = unserialize($shop_app->model_slug)['type'];
            $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.singleContent'));
            $client = hg_hash_sha1($data, $shop_app->appkey, $shop_app->appsecret);
            try {
                $result = $client->request('post', $url);
            } catch (\Exception $e) {
                event(new ErrorHandle($e,'app'));
                FailContentSyn::insert(['route'=>$url,'input_data'=>json_encode(['param'=>$data,'header'=>request()->header()]),'create_time'=>hg_format_date(),'shop_id'=>$event->shop_id]);
                return false;
            }
            $response = json_decode($result->getBody()->getContents(), 1);
            event(new CurlLogsEvent(json_encode($response), $client, $url));
        }
    }
}
