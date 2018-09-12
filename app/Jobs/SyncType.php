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

class SyncType implements ShouldQueue
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
        $this->param = $this->formatData();
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
            //$this->createSyncType($this->param);
        }
    }

    /**
     * 数据处理
     *
     * @return array
     */
    private function formatData()
    {
        $data = [];
        foreach ($this->param as $item) {
            $indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : [];
            $data['data'][] = [
                'ori_id'   => $item->id,
                'title'    => $item->title,
                'indexpic' => [
                    'source'     => 'duanshu',
                    'filepath'   => isset($indexpic['file']) ? $indexpic['file'] : '',
                    'filename'   => '',
                    'host'       => isset($indexpic['host']) ? $indexpic['host'] : '',
                    'filesize'   => 0,//hg_get_file_size($indexpic['host'].$indexpic['file']),
                    'dir'        => '',
                ],
                'is_show'  => $item->status ? true : false
            ];
        }
        $model = unserialize($this->app_info->model_slug)['type'];
        $data['model'] = $model;
        return $data;
    }

    /**
     * 同步成功插入数据库
     *
     * @param $type
     */
    private function createSyncType($type)
    {
        if ($type['data']) {
            foreach ($type['data'] as $item) {
                $data[] = [
                    'shop_id'  => $this->app_info->shop_id,
                    'title'    => $item->title,
                    'indexpic' => serialize($item->indexpic),
                    'is_show'  => $item->is_show ? 1 : 0
                ];
            }
        \App\Models\SyncType::insert($data);
        }
    }
}
