<?php

namespace App\Jobs;

use App\Events\AppEvent\AppSyncFailEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\SyncMember;
use App\Models\FailContentSyn;

class SyncMemberContents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $param;
    protected $app_info;

    /**
     * SyncContents constructor.
     * @param $data
     * @param $shop_app
     */
    public function __construct($data,$shop_app)
    {
        $this->param = $data;
        $this->app_info = $shop_app;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->validateMember($this->param);
        if($data) {
            $client = hg_hash_sha1($data, $this->app_info->appkey, $this->app_info->appsecret);
            $url = str_replace('{app_id}', $this->app_info->appkey, config('define.dingdone.api.member'));
            try {
                $result = $client->request('post', $url);
            } catch (\Exception $exception) {
                event(new ErrorHandle($exception, 'app'));
                FailContentSyn::insert(['route'=>$url,'input_data'=>json_encode($data),'create_time'=>hg_format_date(),'shop_id'=>$this->app_info->shop_id]);
                return true;
            }
            $response = json_decode($result->getBody()->getContents(), 1);
//            记录curl日志
            event(new CurlLogsEvent(json_encode($response), $client, $url));
            if ($response['error_code'] == 0) {
                $this->syncMember($data);
            }
        }
    }

    private function validateMember($member)
    {
        $content = $contents = [];
        foreach ($member as $key=>$item){
            $content['user_list'][$key]['username'] = $item->uid;
            $content['user_list'][$key]['uid'] = $item->uid;
            $content['user_list'][$key]['nick_name'] = $item->nick_name ? : 'ds_'.$item->uid;
            $content['user_list'][$key]['gender'] = $item->sex == 1 ? 1 : ($item->sex == 2 ? 0 : 2);
            $item->mobile && $content['user_list'][$key]['mobile'] = $item->mobile;
            $item->email && $content['user_list'][$key]['email'] = $item->email;
            $item->avatar && $content['user_list'][$key]['avatar'] = $item->avatar;
        }
        $content && $contents['user_list'] = array_values($content['user_list']);
        return $contents;
    }

    /**
     * 同步会员记录
     * @param $member
     */
    private function syncMember($member)
    {
        $uid = SyncMember::whereIn('uid',array_column($member['user_list'],'id'))->pluck('uid')->toArray();
        $data = [];
        foreach ($member['user_list'] as $key => $item) {
            if(!in_array($item['uid'],$uid)) {
                $data[] = [
                    'uid' => $item['uid'],
                    'shop_id' => $this->app_info->shop_id
                ];
            }
        }
        $data && SyncMember::insert($data);
    }
}
