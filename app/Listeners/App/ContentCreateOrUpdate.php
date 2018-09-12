<?php

namespace App\Listeners\App;


use App\Events\AppEvent\AppColumnEvent;
use App\Events\AppEvent\AppContentDeleteEvent;
use App\Events\AppEvent\AppContentEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Jobs\AppContentSync;
use App\Models\AppContent;
use App\Models\ShopApp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ContentCreateOrUpdate
{


    /**
     * Handle the event.
     * @param  AppContentEvent  $event
     * @return void
     */
    public function handle(AppContentEvent $event)
    {
        $shop_app = ShopApp::where('shop_id', $event->data->shop_id)->first();
        if ($shop_app && $shop_app->appkey && $shop_app->appsecret) {
            if ($event->data->payment_type == 1 || $event->data->payment_type == 4) {
                $column = $event->data->column;
                $column && event(new AppColumnEvent($column));
            }
            if ($event->data->state == 2) {   //下架，删除内容
                event(new AppContentDeleteEvent(['content_id' => $event->data->hashid, 'shop_id' => $event->data->shop_id, 'type' => $event->data->type]));
            } elseif ($event->data->up_time > time()) {     //待上架，延迟同步
               $key = $this->clearDeplyQueue($event);
               dispatch((new AppContentSync($event->data))->delay($event->data->up_time - time()));
               Cache::forever($key,$event->data->up_time);
            } else {

                $param = [];
                switch ($event->data->type) {
                    case 'audio':
                        $param = $this->audioParam($event->data, $shop_app);
                        break;
                    case 'article':
                        $param = $this->articleParam($event->data, $shop_app);
                        break;
                    case 'video':
                        $param = $this->videoParam($event->data, $shop_app);
                        break;
                    case 'live':
                        $param = $this->aliveParam($event->data, $shop_app);
                        break;
                }
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
                $this->clearDeplyQueue($event);//执行成功将延迟的队列任务清除

            }

        }
    }

    /**
     * 删除延迟的队列任务
     * @param $event
     * @return string
     */
    private function clearDeplyQueue($event){
        $key = 'content:app:delay:sync:'.$event->data->hashid;
        $score = Cache::get($key);
        Redis::ZREMRANGEBYSCORE('queues:default:delayed',$score,$score);
        return $key;
    }

    private function setResponse($data,$result){
        $app_content = AppContent::where(['shop_id'=>$data->shop_id,'content_id'=>$data->hashid,'content_type'  => $data->type])->first();
        if(!$app_content){
            $app_content = new AppContent();
        }
        $app_content->setRawAttributes([
            'shop_id'       => $data->shop_id,
            'content_id'    => $data->hashid,
            'content_type'  => $data->type,
            'app_content_id'=> $result['result']['id'],
            'source'        => 1,
        ]);
        $app_content->save();
    }


    private function articleParam($data,$shop_app){
        $param['model']= unserialize($shop_app->model_slug)['article'];
        $article = $this->formatArticleParam($data);
        $articlePublic = $this->publicParams($data);
        $param['data'] = array_merge($article,$articlePublic);
        return $param;
    }

    private function audioParam($data,$shop_app){
        $param['model']= unserialize($shop_app->model_slug)['audio'];
        $audio = $this->formatAudioParam($data);
        $audioPublic = $this->publicParams($data);
        $param['data'] = array_merge($audio,$audioPublic);
        return $param;
    }

    private function videoParam($data,$shop_app){
        $param['model']= unserialize($shop_app->model_slug)['video'];
        $video = $this->formatVideoParam($data);
        $videoPublic = $this->publicParams($data);
        $param['data'] = array_merge($video,$videoPublic);
        return $param;
    }

    private function aliveParam($data,$shop_app){
        $param['model']= unserialize($shop_app->model_slug)['live'];
        $alive = $this->formatLiveParam($data);
        $alivePublic = $this->publicParams($data);
        $param['data'] = array_merge($alive,$alivePublic);
        return $param;
    }

    private function formatVideoParam($data){
        $indexpic = hg_unserialize_image_link($data->indexpic);
        $video_file_time = 0;//isset($item->video->videoInfo->url) ?  hg_get_file_time($item->video->videoInfo->url,1) : '0'; //video里面的时长，字符串格式
        $video_indexpic = isset($data->video->videoInfo->cover_url) ?  hg_unserialize_image_link($data->video->videoInfo->cover_url,1) : [];

        $info = [
            'file_size'          => isset($data->video->size) ? (int)($data->video->size) : 0,
            'duration'           => 0,
            'author'             => '',
            'summary'            => $data->brief,
            'item_imgs'          => [],
            'author_pic'         => [
                'source'     => 'duanshu',
                'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                'filename'   => '',
                'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                'filesize'   => 0,//hg_get_file_size($indexpic['host'].$indexpic['file']),
                'dir'        => '',
            ],
            'video'     => [
                "indexpic" => [
                    'source'     => 'duanshu',
                    'filepath'   => isset($video_indexpic['file']) ? $video_indexpic['file'] : '',
                    'filename'   => '',
                    'host'       => isset($video_indexpic['host']) ? $video_indexpic['host'] : '',
                    'filesize'   => 0,//hg_get_file_size($video_indexpic['host'].$video_indexpic['file']),
                    'dir'        => '',
                ],
                "title" => $data->title,
                "duiation" => isset($data->video->videoInfo->url) ? (hg_get_file_time($data->video->videoInfo->url,1) ? : '00:00') : '00:00',
                "author" => "",
                "m3u8" => isset($data->video->videoInfo->url) ? $data->video->videoInfo->url : 'http://'
            ]
        ];
        $data->column && $info['content_column'] = $data->column ? $data->column->hashid : '';
        return $info;
    }


    private function formatAudioParam($data){
        $indexpic = hg_unserialize_image_link($data->indexpic);
        $fielsize = 0;//hg_get_file_size($indexpic['host'].$indexpic['file']);
        $info = [
            'item_imgs'      => [],
            'author'         => '',
            'author_pic'     => [
                'source'     => 'duanshu',
                'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                'filename'   => '',
                'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                'filesize'   => 0,//hg_get_file_size($indexpic['host'].$indexpic['file']),
                'dir'        => '',
            ],
            'file_size'      => $data->audio ? (int)$data->audio->size : 0,
            'duration'       => $data->audio ? hg_get_file_time($data->audio->url): 0,
            'tags'           => [],
            'summary'        => $data->brief ? : '',
            'link'           => $data->audio ? $data->audio->url : 'http://',
            ];
        $data->column && $info['content_column'] = $data->column ? $data->column->hashid : '';
        return $info;
    }

    private function formatArticleParam($data){
        $indexpic = hg_unserialize_image_link($data->indexpic);
        $param = [
            'author'         => '',
            'tags'           => [],
            'content'        => $data->article ? $data->article->content : '',
            'item_imgs'      => [],
            'author_pic'     => [
                'source'     => 'duanshu',
                'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                'filename'   => '',
                'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                'filesize'   => hg_get_file_size($indexpic['host'].$indexpic['file']),//0,
                'dir'        => '',
            ],
            'summary'        => $data->brief,
        ];
        $data->column && $param['content_column'] = $data->column ? $data->column->hashid : '';
        return $param;
    }

    private function formatLiveParam($data){
        $url = DUANSHU_DINGDONE_DOMAIN.'/'.$data->shop_id.'/form/studio/'.$data->hashid.'/discuss';
        $info = [
            'start_time'       => $data->alive ? hg_format_date($data->alive->start_time) : hg_format_date(),
            'end_time'         => $data->alive ? hg_format_date($data->alive->end_time) : hg_format_date(),
            'item_imgs'        => [],
            'summary'          => $data->alive ? $data->alive->live_describe : '',
//                'event'            => 'dingdone://browser?new_window=&url='.$url,
            '343f1284a29c11e79a0b0242301562cc'         => $url,         //直播地址
        ];
        $data->column && $info['content_column'] = $data->column ? $data->column->hashid : '';
        return $info;
    }

    private function publicParams($data){
        $indexpic = hg_unserialize_image_link($data->indexpic);
        $param =  [
            'ori_id'         => $data->hashid,
            'status'         => 2,
            'publish_time'   => hg_format_date(),
            'sub_title'      => $data->title,
            'order_id'       => 0,
            'like_enabled'   => true,
            'favor_enabled'  => true,
            'is_virtual'     => true,
            'is_listing'     => true,
            'num'            => 9999,
            'unit_price'     => [
                'origin'     => (float)$data->price,
                'now'        => (float)$data->price,
            ],
            'indexpic'       =>[
                'source'     => 'duanshu',
                'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                'filename'   => '',
                'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                'filesize'   => 0,//hg_get_file_size($indexpic['host'].$indexpic['file']),
                'dir'        => '',
            ],
            'title'          => $data->title,
            'charge_type'    => isset(config('define.payment_type')[$data->payment_type]) ? config('define.payment_type')[$data->payment_type] : '免费',
            'iscomment'      => true,
            'sold_num'       => intval($data->subscribe),
            'auto_send'      => 1
        ];

        if($data->payment_type == 4){
            $param['sold_separately'] = true;
            $param['charge_type'] = '专栏订阅';
        }
        return $param;
    }



}
