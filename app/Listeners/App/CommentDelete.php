<?php

namespace App\Listeners\App;

use App\Events\AppEvent\AppCommentEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\Comment;
use App\Models\FailContentSyn;
use App\Models\ShopApp;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class CommentDelete
{
    /**
     * Handle the event.
     *
     * @param  AppCommentEvent  $event
     * @return void
     */
    public function handle(AppCommentEvent $event)
    {
        $shop_id = Comment::where('id',$event->comment_id)->value('shop_id');
        $shop_app = ShopApp::where('shop_id',$shop_id)->first();
        if ($shop_app) {
            $url = str_replace(['{app_id}','{ori_id}'],[$shop_app->appkey,$event->comment_id], config('define.dingdone.api.commentsDelete'));
            $client = hg_hash_sha1(['ori_id'=>$event->comment_id],$shop_app->appkey,$shop_app->appsecret);
            try{
                $result = $client->request('delete',$url);
            } catch (\Exception $e) {
                event(new ErrorHandle($e,'app'));
                FailContentSyn::insert(['route'=>$url,'input_data'=>json_encode(['param'=>$event->comment_id,'header'=>request()->header()]),'create_time'=>hg_format_date(),'shop_id'=>$shop_id]);
                return false;
            }
            $response = json_decode($result->getBody()->getContents(),1);
            event(new CurlLogsEvent(json_encode($response),$client,$url));
        }
    }
}
