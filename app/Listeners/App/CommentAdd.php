<?php

namespace App\Listeners\App;

use App\Events\AppEvent\AppCommentAddEvent;
use App\Events\AppEvent\AppContentEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\AppContent;
use App\Models\Content;
use App\Models\FailContentSyn;
use App\Models\ShopApp;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class CommentAdd implements ShouldQueue
{

    /**
     * 队列名称
     * @var string
     */
    public $queue = DEFAULT_QUEUE;

    /**
     * Handle the event.
     *
     * @param  AppCommentAddEvent  $event
     * @return void
     */
    public function handle(AppCommentAddEvent $event)
    {
        $data = $event->data;
        $shop_app = ShopApp::where('shop_id',$event->shop_id)->first();
        if($shop_app) {
            //如果评论的内容没有同步到APP，先同步内容数据
            $content_type = ['article','audio','video','live'];
            if(!AppContent::where('content_id',$data[0]['ori_content_id'])->whereIn('content_type',$content_type)->value('id')){
                $content = Content::where('hashid',$data[0]['ori_content_id'])->first();
                event(new AppContentEvent($content));
            }
            $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.commentAdd'));
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
            if($response['error_code'] == 0){
                $this->syncComment($data,$event->shop_id);
            }
        }
    }



    /**
     * 评论同步记录
     * @param $data
     */
    private function syncComment($data,$shop_id)
    {
        \App\Models\SyncComment::insert(['comment_id' => $data[0]['ori_id'], 'shop_id' => $shop_id]);
    }
}
