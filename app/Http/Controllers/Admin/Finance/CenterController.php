<?php
/**
 * 订单中心相关接口
 */
namespace App\Http\Controllers\Admin\Finance;

use App\Events\CurlLogsEvent;
use App\Events\SystemEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Http\Requests\CenterList;
use App\Http\Requests\CenterStatus;
use GuzzleHttp\Client;

class CenterController extends BaseController
{
    /**
     * 列表接口
     * @param CenterList $request
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function lists(CenterList $request)
    {
        $param = $this->listParam($request->input()); //参数处理
        $client = $this->initClient($param); //初始化 client
        $url = config('define.order_center.api.order_list');
        try {
            $res = $client->request('GET',$url,['query'=>$param]);
        }catch (\Exception $exception){
            $this->error('error_order');
        }
        $result = $this->errorReturn($res); //出错处理和接收数据
        $data = $this->getFineData($result->result); //处理返回数据格式
        event(new CurlLogsEvent(json_encode($result),$client,$url));
        return $this->output($data); //正确返回
    }

    /**
     * 详情接口
     * @param $id
     * @return mixed
     */
    public function detail($id)
    {
        $client = $this->initClient(); //初始化 client
        $url = str_replace('{order_no}',$id,config('define.order_center.api.order_detail'));
        try {
            $res = $client->request('GET',$url);
        }catch (\Exception $exception){
            $this->error('error_order');
        }
        $result = $this->errorReturn($res); //出错处理和接收数据
        event(new CurlLogsEvent(json_encode($result),$client,$url));

        return $this->output($result->result); //正确返回

    }

    /**
     * 修改订单状态接口
     * @param CenterStatus $request
     * @return mixed
     */
    public function status(CenterStatus $request)
    {
        $param = $this->paramStatus($request->input());
        $client = $this->initClient($param); //初始化 client
        $url = str_replace('{order_no}',request('order_no'),config('define.order_center.api.order_status'));
        try {
            $res = $client->request('PUT',$url,['query'=>$param]);
        }catch (\Exception $exception){
            $this->error('error_order');
        }
        $result = $this->errorReturn($res); //出错返回
        event(new CurlLogsEvent(json_encode($result),$client,$url));
        return $this->output($result->result); //正确返回
    }

    public function export()
    {
        //暂不对接
    }

    /**
     * 冻结订单接口 （暂时不用）
     * @return mixed
     */
    public function blocked()
    {
        $this->validateWithAttribute(['order_no' => 'required|string','blocked'=>'required|numeric'],
            ['order_no' => '订单号','blocked'=>'冻结状态']);
        $param = $this->paramBlocked();
        $client = $this->initClient($param); //初始化 client
        $url = config('define.order_center.api.order_list').$param['order_no'].'/blocked/';
        try{
            $res = $client->request('PUT',$url,['query'=>$param]);
        }catch(\Exception $exception){
            $this->error('error_order');
        }
        $result = $this->errorReturn($res); //出错返回
        event(new CurlLogsEvent(json_encode($result),$client,$url));
        return $this->output($result->result); //正确返回

    }

    /**
     * 总收入
     */
    public function incomeTotal(){
        $param = $this->paramTotal();
        $client = $this->initClient(); //初始化 client
        $url = config('define.order_center.api.order_total');
        try {
            $res = $client->request('GET',  $url,['query'=>$param]);
        }catch (\Exception $exception){
            $this->error('error_order');
        }
        $result = $this->errorReturn($res); //出错处理和接收数据
        event(new CurlLogsEvent(json_encode($result),$client,$url));
        return $this->output($result->result); //正确返回
    }

    /**
     * 列表参数出来
     */
    private function listParam($input)
    {
        $arr = ['page', 'size', 'order_number', 'platform', 'pay_channel', 'status', 'blocked', 'trade_time_start', 'trade_time_end', 'trade_total_start', 'trade_total_end', 'channel_slug', 'buyer_uid', 'buyer_platform'];
        $data = [];
        foreach ($input as $key => $val){
            in_array($key,$arr) && $data[$key] = $val;
        }
//        $data['seller_uid'] = $this->shop['id'];
        $data['platform'] = PLATFORM ;
        return $data;
    }

    /**
     * 状态参数出来
     */
    private function paramStatus($input){
        $arr = [ 'status', 'exception_reason', 'buyer_message', 'pay_channel', 'transaction_no', 'receipt_amount', 'orderitems', 'id', 'delivery_type', 'delivery_no',
        ];
        $data = [];
        foreach ($input as $key => $val){
            in_array($key,$arr) && $data[$key] = $val;
        }
        return $data;
    }

    /**
     * 冻结参数
     */
    private function paramBlocked(){
        return [
            'order_no' => request('order_no'),
            'blocked' => request('blocked')
        ];
    }

    /**
     * 总收入参数
     */
    private function paramTotal(){
        return [
            'seller_uid' => $this->shop['id']
        ];
    }

    /**
     * 整理列表返回数据
     */
    private function getFineData($param){
        $data = [];
        $data['page'] = [
            'total'=>$param->count? : 0,
            'current_page' => (int)request('page'),
            'last_page'     => ceil($param->count/request('size')),
        ];
        foreach ($param->data as $k=>$item){
            $data['data'][] = [
                'id' => $item->id,
                'pay_channel' => $item->pay_channel,
                'user_id' => $item->buyer->uid,
                'nickname' => $item->buyer->nickname,
                'real_price' => $item->receipt_amount,
                'status'    => $item->status,
                'remark'    => $item->buyer_message,
//                'avatar'   => isset($item->extra_data->avatar)? $item->extra_data->avatar : '',
                'order_id' => $item->order_no,
                'order_time' => $item->create_time? date('Y-m-d H:i:s',$item->create_time) : '',
//                'content_type' => isset($item->orderitems[0]->sku->type) ? $item->orderitems[0]->sku->type : '',
                'shop_id'      => $item->seller->uid,
                'goods_info'   => $item->orderitems,
            ];
        }
        return $data;

    }

    private function initClient($data = '',$method = 'get')
    {
        $appId = config('define.order_center.app_id');
        $appSecret = config('define.order_center.app_secret');
        $timesTamp = time();
        $param = [
            'access_key' => $appId,
            'access_secret' => $appSecret,
            'timestamp'     => $timesTamp,
        ];
        if($data){
            $param['raw_data'] = json_encode($data);
        }

        $sign = hg_hash_sha256($param);
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-API-SIGNATURE' => $sign,
                'x-API-KEY' => $appId,
                'x-API-TIMESTAMP' => $timesTamp,
            ],
//            'http_errors'   => false,
            'body'  => $data ? json_encode($data) : '',
        ]);
        return $client;
    }

    private function errorReturn($res)
    {

        if($res->getStatusCode() !== 200){
            $this->error('error-sync-order');
        }
        $data = json_decode($res->getBody()->getContents());

        if($res && $data->error_code){
            $this->errorWithText(
                'error-sync-order-'.$data->error_code,
                $data->error_message
            );
        }
        return $data;
    }

    /**
     * 订单中心事件通知
     * @return array
     */
    public function orderCallback(){
//        if(request('blocked') == 'true'){
//            event(new SystemEvent(request('seller')['uid'],trans('notice.title.blocked'),trans('notice.content.blocked'),0,-1,'系统管理员'));
//        }
//        return [
//            "error_code" => "0",
//            "error_message" => "success",
//        ];

        return 'SUCCESS';
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 资产管理（余额、提现中、可提现）
     */
    public function getAssets(){
        $data = ['uid'=>$this->shop['id']];
        $client = hg_verify_signature($data,'','','',$this->shop['id']);
        $url = config('define.order_center.api.withdraw_money');
        try {
            $res = $client->request('GET',$url,['query'=>$data]);
        } catch (\Exception $e) {
            $res = $e->getMessage();
            event(new CurlLogsEvent(json_encode($res),$client,$url));
            $this->error('error-withdraw-money');
        }
        $data = json_decode($res->getBody()->getContents(),1);
        event(new CurlLogsEvent(json_encode($data),$client,$url));
        if ($res->getStatusCode() !== 200) {
            $this->error('error-withdraw-money');
        }
        if ($res && $data['error_code']) {
            $this->errorWithText('error-withdraw-money-'.$data['error_code'], $data['error_message']);
        }
        if ($data['result']) {
            $data['result']['available'] = isset($data['result']['available']) ? round($data['result']['available'] / 100,2) : 0.00;
            $data['result']['pending'] = isset($data['result']['pending']) ? round($data['result']['pending'] / 100,2) : 0.00;
            $data['result']['confirmed'] = isset($data['result']['confirmed']) ? round($data['result']['confirmed'] / 100,2) : 0.00;
            $data['result']['settling'] = isset($data['result']['settling']) ? round($data['result']['settling']  / 100,2) : 0.00;
            $data['result']['total'] = isset($data['result']['total']) ? round($data['result']['total'] / 100,2) : 0.00;
            $data['result']['income'] = isset($data['result']['income']) ? round($data['result']['income'] / 100,2) : 0.00;
        }
        return $this->output($data['result']);
    }
}