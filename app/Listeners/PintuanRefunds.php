<?php

namespace App\Listeners;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\PintuanRefundsEvent;
use App\Models\Order;
use App\Models\RefundOrder;
use GuzzleHttp\Client;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class PintuanRefunds
{

    /**
     * Handle the event.
     *
     * @param  PintuanRefundsEvent  $event
     * @return void
     */
    public function handle(PintuanRefundsEvent $event)
    {
        $param = $event->param;
        $order_center_no = $param['order_no'];
        $order = Order::where(['order_id'=>$order_center_no])->first();
        $refund_order = RefundOrder::where(['order_id'=>$order_center_no])->first();

        $order_center_no = $order ? $order->center_order_no : '';
        if(!$refund_order) {
            $refund_order = new RefundOrder();
            $refund_no = date('ymdHis') . str_pad(time() + mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
            $refunds_param = [
                'order_no' => $order_center_no,
                'order_id' => $order->order_id,
                'shop_id' => $order->shop_id,
                'refund_no' => $refund_no,
            ];
            $refund_order->setRawAttributes($refunds_param);
            $refund_order->save();
        }

        //请求订单中心获取订单商品id
        $order_item_id = $this->getCenterOrder($order_center_no,$order);
        $param['order_item'] = $order_item_id;
        $param['order_no'] = $order_center_no;
        $client = hg_verify_signature($param);
        $url = config('define.order_center.api.m_order_refunds');
        //如果是线上订单，直接请求线上订单中心接口
        if(getenv('APP_ENV') == 'pre' && $order && $order->channel == 'production') {
            $url = str_replace('storetest', 'store', $url);
            $client = hg_verify_signature($param,'',env('ORDER_CENTER_PRODUCTION_APPID'),env('ORDER_CENTER_PRODUCTION_APPSECRET'));
        } else if(getenv('APP_ENV') == 'production' && $order && $order->channel == 'pre'){
            $url = str_replace('store', 'storetest', $url);
            $client = hg_verify_signature($param,'',env('ORDER_CENTER_PRE_APPID'),env('ORDER_CENTER_PRE_APPSECRET'));
        }
        try{
            $res = $client->request('POST',$url);
            $refunds_return = $res->getBody()->getContents();
            event(new CurlLogsEvent($refunds_return,$client,$url));
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
            return false;
        }
        $return = json_decode($refunds_return);
        //退款申请失败,不包括订单已关闭、已申请
        if($return && $return->error_code && !in_array($return->error_code,[6059,6062])){
            $refund_order->refund_status = 3;
            $refund_order->save();
            if($order){
                $order->pay_status = -5;//退款失败
                $order->save();
            }
        }else{
            $refund_order->refund_status = 2;
            isset($return->result->id) && $refund_order->order_center_refund_id = $return->result->id;
            $refund_order->save();
            //如果有多条退款记录更新所有的状态
            if(RefundOrder::where('order_no',$order_center_no)->count() > 1){
                RefundOrder::where('order_no',$order_center_no)->update(['refund_status'=>2]);
            }
            //退款申请提交成功
            if($order){
                $order->pay_status = -4;//订单已退款
                $order->save();
            }


        }

    }

    //获取订单信息
    private function getCenterOrder($id,$order){

        $client = hg_verify_signature();
        $url = str_replace('{order_no}', $id, config('define.order_center.api.order_detail'));
        if(getenv('APP_ENV') == 'pre' && $order && $order->channel == 'production') {
            $url = str_replace('storetest', 'store', $url);
            $client = hg_verify_signature([],'',env('ORDER_CENTER_PRODUCTION_APPID'),env('ORDER_CENTER_PRODUCTION_APPSECRET'));
        } else if(getenv('APP_ENV') == 'production' && $order && $order->channel == 'pre'){
            $url = str_replace('store', 'storetest', $url);
            $client = hg_verify_signature([],'',env('ORDER_CENTER_PRE_APPID'),env('ORDER_CENTER_PRE_APPSECRET'));
        }
        try {
            $return = $client->request('GET',$url);
            $response = $return->getBody()->getContents();
            event(new CurlLogsEvent($response,$client,$url));
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
            return '';
        }
        $response = json_decode($response);
        if($response && !$response->error_code && $response->result){
            return $response->result->orderitems[0]->id;
        }
        return '';
    }
}
