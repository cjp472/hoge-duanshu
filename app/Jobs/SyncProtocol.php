<?php

namespace App\Jobs;

use App\Models\ShopProtocol;
use App\Models\VersionOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncProtocol implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $shopId = '';

    public function __construct($shopId)
    {
        $this->shopId = $shopId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $obj = VersionOrder::select('success_time','sku','total')->where(['type'=>'permission','shop_id'=>$this->shopId])->orderBy('success_time','desc')->first();
        if($obj){
            $sku = unserialize($obj->sku);
            $content = [
                'name' => '',
                'title' => "\"短书\"软件服务订购协议",
                'price' => $obj->total,
                'time' => $sku['properties'][0]['v']
            ];
            $data = [
                'p_id' => 1,
                'shop_id' => $this->shopId,
                'create_time' => $obj->success_time,
                'content' => serialize($content),
                'status' => 1
            ];
            $info = ShopProtocol::where('shop_id',$this->shopId)->value('id');
            if(!$info){
                ShopProtocol::insert($data);
            }
        }
    }
}
