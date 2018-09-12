<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CheckOrderStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $request;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $res = [];
        $order = Order::whereIn('pay_status', [-1, -2])
            ->where('center_order_no', '!=', '')
            ->where('order_type',1)
//            ->where('channel', env('APP_ENV'))
            ->paginate(isset($this->request['count']) ? $this->request['count'] : 100,['id','center_order_no', 'shop_id', 'pay_status','channel'],'page',$this->request['page']);
        foreach ($order as $item) {
            $client = hg_verify_signature();
            $url = str_replace('{order_no}',$item->center_order_no,config('define.order_center.api.order_detail'));
            if(getenv('APP_ENV') == 'pre' && $item && $item->channel == 'production') {
                $url = str_replace('storetest', 'store', $url);
                $client = hg_verify_signature([],'',env('ORDER_CENTER_PRODUCTION_APPID'),env('ORDER_CENTER_PRODUCTION_APPSECRET'));
            } else if(getenv('APP_ENV') == 'production' && $item->channel == 'pre'){
                $url = str_replace('store', 'storetest', $url);
                $client = hg_verify_signature([],'',env('ORDER_CENTER_PRE_APPID'),env('ORDER_CENTER_PRE_APPSECRET'));
            }
            try {
                $return = $client->request('GET',$url);
            }catch (\Exception $exception){
                continue;
            }
            $response = json_decode($return->getBody()->getContents());
            if($response && !$response->error_code && $response->result){
                $result = $response->result;
                $status = [
                    'success'   => 1,
                    'closed'    => -1,
                    'unpaid'    => 0,
                ];
                $order_pay_status = isset($status[$result->status]) ? $status[$result->status] : 1;
                if($order_pay_status != $item->pay_status){
                    $res[] = [
                        'order_no'  => $item->center_order_no,
                        'last'      => $item->pay_status,
                        'new'       => $order_pay_status,
                    ];
                    $item->pay_status = $order_pay_status;
                    $item->save();
                }
            }
        }
        file_put_contents(storage_path('logs/order.txt'),var_export($res,1),FILE_APPEND);
    }
}
