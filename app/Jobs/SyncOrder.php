<?php

namespace App\Jobs;

use App\Events\AppEvent\AppSyncFailEvent;
use App\Events\CurlLogsEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $param;
    protected $url;
    protected $app_info;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($param,$url,$app_info)
    {
        $this->param = $param;
        $this->url = $url;
        $this->app_info = $app_info;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = hg_hash_sha1($this->param,$this->app_info->appkey,$this->app_info->appsecret);
        try {
            $result = $client->request('post', $this->url);
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'app'));
            event(new AppSyncFailEvent($this->url,json_encode(['param'=>$this->param,'header'=>request()->header()]),$this->app_info->shop_id));
            return true;
        }
        $response = json_decode($result->getBody()->getContents(),1);
        //记录curl日志
        event(new CurlLogsEvent(json_encode($response),$client,$this->url));
        if($response['error_code'] == 0){
            $this->syncOrder($this->param);
        }
    }


    /**
     * 购买关系同步记录
     * @param $order
     */
    private function syncOrder($order)
    {
        if ($order) {
            foreach ($order as $item) {
                $data[] = [
                    'uid'        => $item['uid'],
                    'content_id' => $item['content_id'],
                    'group_id'   => $item['group_id'],
                    'shop_id'    => request('shop_id')
                ];
            }
            \App\Models\SyncOrder::insert($data);
        }
    }
}
