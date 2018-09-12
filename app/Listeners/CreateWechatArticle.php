<?php

namespace App\Listeners;

use App\Events\CreateWechatArticleEvent;
use App\Events\CurlLogsEvent;
use App\Http\Controllers\Admin\OpenPlatform\Publics\QcloudController;
use App\Models\Article;
use App\Models\Content;
use App\Models\PublicArticle;
use GuzzleHttp\Client;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use qcloudcos\Cosapi;
use Vinkla\Hashids\Facades\Hashids;

class CreateWechatArticle implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = DEFAULT_QUEUE;

    /**
     * Handle the event.
     *
     * @param  CreateWechatArticleEvent  $event
     * @return void
     */
    public function handle(CreateWechatArticleEvent $event)
    {
        $shop_id = $event->shop_id;
        $media_id = $event->media_id;

        $article = PublicArticle::where(['media_id'=>$media_id])->orderByDesc('id')->value('content');
        if($article){
            $params = unserialize($article);
            $time = time();
            $ret = null;
            // content --start
            $content = new Content();
            $content->shop_id = $shop_id;
            $content->title = $params['title'];
            $content->indexpic = $params['thumb_url'] ? (new QcloudController())->uploadUrl($params['thumb_url']) : '';
            $content->payment_type = 3;
            $content->column_id = 0;
            $content->price = 0;
            $content->up_time = $time;
            $content->create_time = time();
            $content->update_time = time();
            $content->create_user = $event->user['id'];
            $content->update_user = 0;
            $content->state = 1;
            $content->display = 1;
            $content->is_lock = 0;
            $content->type = 'article';
            $content->comment_count = 0;
            $content->view_count = 0;
            $content->subscribe = 0;
            $content->play_count = 0;
            $content->end_play_count = 0;
            $content->share_count = 0;
            $content->brief = $params['digest'];
            $ret = $content->save();
            if ($ret) {
                $content->hashid = Hashids::encode($content->id);
                $content->save();
                // article --start
                $article = new Article();
                $article->content_id = $content->hashid;
                $article->content = $this->switchImg($params['content']);
                $article->save();
            }
            PublicArticle::where(['media_id'=>$media_id])->delete();
        }


    }

    private function switchImg($content)
    {
        preg_match_all('/ (data-)?src=\"(.*?)\"/', $content, $images);
        foreach ($images[2] as $k => $url) {
            $img = $this->uploadUrl($url);
            // 替换内容中的图片链接
            $content = str_replace($images[0][$k], ' src="'. $img .'"', $content);
        }
        return $content;
    }


    private function uploadUrl($url)
    {
        $file_name = md5($url);
        $cos_path = config('qcloud.folder') . '/image/' . $file_name;
        $upload_path = resource_path('material/openfaltform/'). $file_name;
        try {
            $ret = (new Client(['verify' => false]))->get($url, ['save_to' => $upload_path]);
        } catch (\Exception $e) {
            return $url;
        }
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'), $upload_path, $cos_path, null, null, 0);
        // 更新文件属性
        Cosapi::update(config('qcloud.cos.bucket'), $cos_path, '', '', ['Content-Type' => $ret->getHeaders()['Content-Type'][0]]);
        if(file_exists($upload_path)) {
            unlink($upload_path);
        }
        event(new CurlLogsEvent(json_encode($data),new Client(),'http://region.file.myqcloud.com/files/v2/'));
        return isset($data['data']['source_url']) ? $data['data']['source_url'] : '';
    }
}
