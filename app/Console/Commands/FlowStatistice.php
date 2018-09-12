<?php

namespace App\Console\Commands;

use App\Events\ErrorHandle;
use App\Models\ShopFlow;
use App\Models\Video;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class FlowStatistice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flowStatistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每天中午12点统计店铺视频访问流量';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $now_time = date('Y-m-d',time());
        $time = date_add(date_create($now_time),date_interval_create_from_date_string('-1 days'));
        $secret_key = config('qcloud.secret_key');
        $secret_id = config('qcloud.secret_id');
        $arg_list = [
            "Action"    => 'GetPlayStatLogList',
            "from"      => date_format($time,'Y-m-d'),
            "to"        => date_format($time,'Y-m-d'),
        ];
        $result = [];
        try{
            $result = \QcloudApi_Common_Request::send($arg_list, $secret_id, $secret_key, 'POST', config('qcloud.delete.host'), config('qcloud.delete.path'));
        }catch (\Exception $e){
            event(new ErrorHandle($e,'tencent_cloud'));
        }
        if ($result['code'] != 0 && $result['code'] != 4000) {
            $this->errorWithText($result['code'],$result['message']);
        }
        if($result){
            $data = $this->getFlowData($result);
            if($data){
                foreach ($data as $value){
                    $item = explode(',',$value);
                    $shop_id = Video::where('file_id',$item[1])->value('shop_id')?:'';
                    $param = [
                        'shop_id'   => $shop_id,
                        'numberical' => $item[4]/1024,      //单位kb
                        'remark'    => 'flow',
                        'time'      => time(),
                        'unit_price'=> 0,
                        'price'     => 0,
                        'flow_type' => 0,
                    ];
                    $now_time = date('Y-m-01 00:00:00',time());
                    $time = date_add(date_create($now_time),date_interval_create_from_date_string('1 months'));
                    $start_time = strtotime(date('Y-m-01 00:00:00',time()));
                    $end_time = strtotime(date_format($time,'Y-m-d H:i:s'));
                    $flow = ShopFlow::where(['remark'=>'flow','shop_id'=>$shop_id,'flow_type'=>0])->whereBetween('time',[$start_time,$end_time])->sum('numberical');
                    if(DEFAULT_FLOW - $flow < 0 ){
                        $param['unit_price'] = DEFAULT_FLOW_UNIT_PRICE;
                        $param['price'] = DEFAULT_FLOW_UNIT_PRICE*($item[4]/(1048576*1024));
                        $param['flow_type'] = 1;
                    }
                    $response[] = $param;
                }
            }
            ShopFlow::insert($response);
        }
    }


    private function getFlowData($result){
        $item = $result['fileList'][0];
        $client = new Client();
        $path = resource_path('material/flow/');
        !is_dir($path) && mkdir($path, 0777, 1);
        $response = $client->request('get', $item['url'])->getBody()->getContents();
        $flow = $path . md5($item['url']).'.csv.gz';
        file_put_contents($flow,$response);
        $data = gzfile($flow);
        unset($data[0]);
        unlink($flow);
        return $data;
    }
}
