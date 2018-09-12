<?php

namespace App\Jobs;

use App\Events\AppEvent\AppSyncFailEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\AppContent;
use App\Models\Column;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Content;

class SyncContents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $param;
    protected $url;
    protected $app_info;
    protected $content_type;

    /**
     * SyncContents constructor.
     * @param $data
     * @param $url
     * @param $shop_app
     * @param $content_type
     */
    public function __construct($data,$url,$shop_app,$content_type)
    {
        $this->param = $data;
        $this->url = $url;
        $this->app_info = $shop_app;
        $this->content_type = $content_type;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = [];
        switch ($this->content_type){
            case 'column' :
                $data = $this->validateColumn($this->param,unserialize($this->app_info->model_slug)['column']);
                break;
            case 'article' :
                $data = $this->validateArticle($this->param,unserialize($this->app_info->model_slug)['article']);
                break;
            case 'audio' :
                $data = $this->validateAudio($this->param,unserialize($this->app_info->model_slug)['audio']);
                break;
            case 'video' :
                $data = $this->validateVideo($this->param,unserialize($this->app_info->model_slug)['video']);
                break;
            case 'live' :
                $data = $this->validateLive($this->param,unserialize($this->app_info->model_slug)['live']);
                break;
            default:
                break;

        }
        if($data) {
            $client = hg_hash_sha1($data, $this->app_info->appkey, $this->app_info->appsecret);
            try {
                $result = $client->request('post', $this->url);
            } catch (\Exception $exception) {
                event(new ErrorHandle($exception, 'app'));
                event(new AppSyncFailEvent($this->url,json_encode(['param'=>$this->param->toArray(),'header'=>request()->header()]),$this->app_info->shop_id));
                return true;
            }
            $response = json_decode($result->getBody()->getContents(), 1);
            //记录curl日志
            event(new CurlLogsEvent(json_encode($response), $client, $this->url));
            if ($response['error_code'] == 0) {
                $this->setResponse($response['result'], $this->content_type, $this->app_info->shop_id);
            }
        }
    }

    //专栏数据整理
    private function validateColumn($column,$model)
    {
        foreach ($column as $key => $item){
            $info = [
                'content_count'  => Content::where('column_id',$item->id)->count() ? : 0,
                'finished'       => intval($item->finish) ? true : false,
                'summary'        => $item->brief,
                'is_free'        => ($item->charge || ($item->price > 0.00)) ? false : true,
                'subscribe_enabled' => true,
                'category_ids'   => $item->column_type->pluck('type_id')->toArray()
            ];
            $common = $this->commonWord($item);
            unset($common['charge_type'],$common['sold_separately']);
            $content['data'][] = array_merge($info,$common);
        }
        $content['model'] = $model;
        return $content;
    }

    //图文数据整理
    private function validateArticle($article,$model)
    {
        foreach ($article as $key=>$item){
            $indexpic = hg_unserialize_image_link($item->indexpic);
            $info = [
                'author'         => '',
                'tags'           => [],
                'content'        => $item->article ? $item->article->content : '',
                'item_imgs'      => [],
                'author_pic'     => [
                    'source'     => 'duanshu',
                    'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                    'filename'   => '',
                    'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                    'filesize'   => hg_get_file_size($indexpic['host'].$indexpic['file']),//0,
                    'dir'        => '',
                ],
                'summary'        => $item->brief,
            ];
            $item->column && $info['content_column'] = $item->column ? $item->column->hashid : '';
            $common = $this->commonWord($item);
            $content['data'][] = array_merge($info,$common);
        }
        $content['model'] = $model;
        return $content;
    }

    //视频数据整理
    private function validateVideo($video,$model)
    {
        foreach ($video as $key=>$item){
            $indexpic = hg_unserialize_image_link($item->indexpic);
            $video_file_time = 0;//isset($item->video->videoInfo->url) ?  hg_get_file_time($item->video->videoInfo->url,1) : '0'; //video里面的时长，字符串格式
            $video_indexpic = isset($item->video->videoInfo->cover_url) ?  hg_unserialize_image_link($item->video->videoInfo->cover_url,1) : [];
            $info = [
                'file_size'          => isset($item->video->size) ? (int)($item->video->size) : 0,
                'duration'           => 0,
                'author'             => '',
                'summary'            => $item->brief,
                'item_imgs'          => [],
                'author_pic'         => [
                    'source'     => 'duanshu',
                    'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                    'filename'   => '',
                    'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                    'filesize'   => hg_get_file_size($indexpic['host'].$indexpic['file']),
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
                    "title" => $item->title,
                    "duiation" => '00:00',//isset($item->video->videoInfo->url) ? hg_get_file_time($item->video->videoInfo->url,1) : "00:00",
                    "author" => "",
                    "m3u8" => isset($item->video->videoInfo->url) ? $item->video->videoInfo->url : 'http://'
                ]
            ];
            $item->column && $info['content_column'] = $item->column ? $item->column->hashid : '';
            $common = $this->commonWord($item);
            $content['data'][] = array_merge($info,$common);
        }
        $content['model'] = $model;
        return $content;
    }

    //音频数据处理
    private function validateAudio($audio,$model)
    {
        foreach ($audio as $key=>$item) {
            $indexpic = hg_unserialize_image_link($item->indexpic);
            $fielsize = 0;//hg_get_file_size($indexpic['host'].$indexpic['file']);
            $info = [
                'item_imgs'      => [],
                'author'         => '',
                'author_pic'     => [
                    'source'     => 'duanshu',
                    'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                    'filename'   => '',
                    'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                    'filesize'   => hg_get_file_size($indexpic['host'].$indexpic['file']),
                    'dir'        => '',
                ],
                'file_size'      => $item->audio ? (int)$item->audio->size : 0,
                'duration'       => $item->audio ? hg_get_file_time($item->audio->url): 0,
                'tags'           => [],
                'summary'        => $item->brief ? : '',
                'link'           => $item->audio ? $item->audio->url : 'http://',
            ];
            $item->column && $info['content_column'] = $item->column ? $item->column->hashid : '';
            $common =  $this->commonWord($item);
            $content['data'][] = array_merge($info,$common);
        }
        $content['model'] = $model;
        return $content;
    }

    //直播数据整理
    private function validateLive($live,$model)
    {
        foreach ($live as $key=>$item) {

            $url = DUANSHU_DINGDONE_DOMAIN.'/'.$item->shop_id.'/form/studio/'.$item->hashid.'/discuss';
            $info = [
                'start_time'       => $item->alive ? hg_format_date($item->alive->start_time) : hg_format_date(),
                'end_time'         => $item->alive ? hg_format_date($item->alive->end_time) : hg_format_date(),
                'item_imgs'        => [],
                'summary'          => $item->alive ? $item->alive->live_describe : '',
//                'event'            => 'dingdone://browser?new_window=&url='.$url,
                '343f1284a29c11e79a0b0242301562cc'         => $url,         //直播地址
            ];
            $item->column && $info['content_column'] = $item->column ? $item->column->hashid : '';
            $common = $this->commonWord($item);
            $content['data'][] = array_merge($info,$common);
        }
        $content['model'] = $model;
        return $content;
    }

    /**
     * @param $response
     * @param $type
     * @param $shop_id
     */
    private function setResponse($response,$type,$shop_id)
    {
        if ($response) {
            $data = AppContent::where(['content_type'=>$type,'shop_id'=>$shop_id])->pluck('app_content_id')->toArray();
            $val = [];
            foreach ($response as $key=>$value){
                if (!in_array($value,$data)) {
                    $val[]=[
                        'shop_id'        => $shop_id,
                        'content_id'     => $key,
                        'content_type'   => $type,
                        'app_content_id' => $value,
                        'source'         => 1,
                    ];
                }
            }
            if ($val) {
                AppContent::insert($val);
            }

        }
    }

    /**
     * 公共字段
     * @param $item
     * @return array
     */
    private function commonWord($item)
    {
        $indexpic = hg_unserialize_image_link($item['indexpic']);
        $sold_separately = isset($item['payment_type']) ? (($item['payment_type'] == 4) ? true : false) : false;
        $param =  [
            'ori_id'         => $item['hashid'],
            'status'         => 2,
            'publish_time'   => hg_format_date(),
            'sub_title'      => $item['title'],
            'order_id'       => 0,
            'like_enabled'   => true,
            'favor_enabled'  => true,
            'is_virtual'     => true,
            'is_listing'     => true,
            'num'            => 9999,
            'unit_price'     => [
                'origin'     => (float)$item['price'],
                'now'        => (float)$item['price'],
            ],
            'indexpic'       =>[
                'source'     => 'duanshu',
                'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                'filename'   => '',
                'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                'filesize'   => 0,//hg_get_file_size($indexpic['host'].$indexpic['file']),
                'dir'        => '',
            ],
            'title'          => $item['title'],
            'charge_type'    => isset(config('define.payment_type')[$item['payment_type']]) ? config('define.payment_type')[$item['payment_type']] : '免费',
//            'sold_separately'=> $sold_separately,
            'iscomment'      => true,
            'sold_num'       => intval($item['subscribe']),
            'auto_send'      => 1
        ];

        if($item['payment_type'] == 4){
            $param['sold_separately'] = true;
            $param['charge_type'] = '专栏订阅';
        }

        return $param;
    }
}
