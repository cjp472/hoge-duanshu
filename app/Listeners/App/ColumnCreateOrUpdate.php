<?php

namespace App\Listeners\App;

use App\Events\AppEvent\AppColumnEvent;
use App\Events\AppEvent\AppContentDeleteEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\AppContent;
use App\Models\Column;
use App\Models\Content;
use App\Models\ShopApp;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ColumnCreateOrUpdate
{


    /**
     * Handle the event.
     * @param  AppColumnEvent  $event
     * @return void
     */
    public function handle(AppColumnEvent $event)
    {

        $shop_app = ShopApp::where('shop_id',$event->data->shop_id)->first();
        if($shop_app && $shop_app->appkey && $shop_app->appsecret){
            if(!$event->data->state){
                event(new AppContentDeleteEvent(['content_id'=>$event->data->hashid,'shop_id'=>$event->data->shop_id,'type'=>'column']));
            }else {
                $param = $this->columnParam($event->data, $shop_app);

                $client = hg_hash_sha1($param, $shop_app->appkey, $shop_app->appsecret, time());
                try {
                    $response = $client->request('POST', str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.singleContent')));
                } catch (\Exception $exception) {
                    event(new ErrorHandle($exception, 'app'));
                    return false;
                }
                $result = json_decode($response->getBody()->getContents(), 1);
                event(new CurlLogsEvent(json_encode($result), $client, str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.singleContent'))));
                if ($result['error_code'] == 0) {
                    $this->setResponse($event->data, $result);
                }
            }
        }
    }

    private function setResponse($data,$result){
        $app_content = AppContent::where(['shop_id'=>$data->shop_id,'content_id'=>$data->hashid,'content_type'  => 'column'])->first();
        if(!$app_content){
            $app_content = new AppContent();
        }
        $app_content->setRawAttributes([
            'shop_id'       => $data->shop_id,
            'content_id'    => $data->hashid,
            'content_type'  => 'column',
            'app_content_id'=> $result['result']['id'],
            'source'        => 1,
        ]);
        $app_content->save();
    }

    private function columnParam($data,$shop_app)
    {
        $indexpic = hg_unserialize_image_link($data->indexpic);
        $param['model'] = unserialize($shop_app->model_slug)['column'];
        $param['data']  = [
            "ori_id"        => $data->hashid,
            "status"        => 2,
            "content_count" => intval(Content::where('column_id',$data->id)->count()),
            "publish_time"  => hg_format_date(),
            "title"         => $data->title,
            "sub_title"     => $data->title,
            "order_id"      => 0,
            "like_enabled"  => true,
            "favor_enabled" => true,
            "is_virtual"    => true,
            "is_listing"    => true,
            "num"           => 100000,
            "unit_price"    => [
                "origin"    => (float)$data->price,
                "now"       => (float)$data->price,
            ],
            "iscomment"     => true,
            "sold_num"      => intval($data->subscribe),
            "auto_send"     => 1,
            "indexpic"      => [
                "source"    => "duanshu",
                "filepath"  => isset($indexpic['file'])?$indexpic['file']:'',
                "filename"  => '',
                "host"      => isset($indexpic['host'])?$indexpic['host']:'',
                "filesize"  => 0,//hg_get_file_size($indexpic['host'].$indexpic['file']),
                "dir"       => ""
            ],
            "summary"       => $data->describe,
            'finished'       => intval($data->finish) ? true : false,
            'is_free'        => ($data->charge || ($data->price > 0.00)) ? false : true,
            'subscribe_enabled' => true,
            'category_ids'   => $data->column_type->pluck('type_id')
        ];
        return $param;
    }


}
