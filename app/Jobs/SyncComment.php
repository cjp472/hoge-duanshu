<?php

namespace App\Jobs;

use App\Events\AppEvent\AppSyncFailEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\Content;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $param;
    protected $url;
    protected $app_info;
    protected $shop_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($param,$url,$app_info,$shop_id)
    {
        $this->param = $param;
        $this->url = $url;
        $this->app_info = $app_info;
        $this->shop_id = $shop_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->validateComment();
        $syncComment = \App\Models\SyncComment::where('shop_id',$this->shop_id)->pluck('comment_id')->toArray();//已存在的评论记录
        $ori_id = [];
        foreach ($data as $item) {
            if(Content::where('hashid',$item['ori_content_id'])->value('id')) {
                if (!in_array($item['ori_id'], $syncComment)) {
                    $client = hg_hash_sha1([$item], $this->app_info->appkey, $this->app_info->appsecret);
                    try {
                        $result = $client->request('post', $this->url);
                    } catch (\Exception $exception) {
                        event(new ErrorHandle($exception, 'app'));
                        event(new AppSyncFailEvent($this->url, json_encode(['param' => [$item], 'header' => request()->header()]), $this->app_info->shop_id));
                        return true;
                    }
                    $response = json_decode($result->getBody()->getContents(), 1);
                    //记录curl日志
                    event(new CurlLogsEvent(json_encode($response), $client, $this->url));
                    if ($response['error_code'] == 0) {
                        $ori_id[] = $item['ori_id'];
                    }
                }
            }
        }
        if ($ori_id) {
            $this->syncComment($ori_id);
        }
    }

    /**
     * 评论同步记录
     * @param $data
     */
    private function syncComment($data)
    {
        foreach ($data as $item) {
            $comment[] = [
                'comment_id' => $item,
                'shop_id'    => $this->shop_id
            ];
        }
        \App\Models\SyncComment::insert($comment);
    }


    //评论数据整理
    private function validateComment()
    {
        $content = [];
        foreach ($this->param as $item){
            $info = [
                'ori_content_id'     => $item['content_id'],
                'ori_id'             => $item['id'],
                'uid'                => $item['member_id'],
                'create_time'        => hg_format_date($item['comment_time']),
                'comment'            => $item['content'],
                'img'                => [],
                'star'               => 0,
                'is_anonymous'       => false,
                'like'               => intval($item['praise'])
            ];
            $item['fid'] && $info['reply_comment_id'] = $item['fid'];
            $content[] = $info;
        }

        return $content;
    }

}
