<?php
/**
 * Created by PhpStorm.
 * User: a123456
 * Date: 2017/5/17
 * Time: 下午6:14
 */

namespace App\Http\Controllers\Admin\Finance;

use App\Events\CurlLogsEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Http\Requests\Goods;
use GuzzleHttp\Client;

class GoodsController extends BaseController
{

    /**
     * 商品列表
     * @param Goods $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGoodsList(Goods $request){
        $data = $this->get_request_data($request->input());  //获取请求数据
        $client = $this->initClient($data);
        $url = config('define.service_store.api.goods_list');
        try {
            $res = $client->request('get',$url,['query'=>$data]);
        }catch (\Exception $exception){
            event(new CurlLogsEvent($exception->getMessage(),$client,$url));
            $this->error('error_order');
        }
        $result = $this->errorReturn($res);
        event(new CurlLogsEvent(json_encode($result),$client,$url));
        return $this->output($result->result);
    }

    /**
     * 参数
     * @param $input
     * @return array
     */
    private function get_request_data($input){
        $arr = ['tags','category','product_type','min_price','max_price'];
        $data = array();
        foreach ($input as $key => $val){
            in_array($key,$arr) && $data[$key] = $val;
        }

        isset($input['size']) && $data['api.size'] = $input['size'];
        isset($input['page']) && $data['api.page'] = $input['page'];
        return $data ? : [];
    }

    /**
     * 签名
     */
    private function initClient($data = '',$method = 'get')
    {
        $appId = config('define.service_store.app_id');
        $appSecret = config('define.service_store.app_secret');
        $timesTamp = time();
        $client = hg_verify_signature($data,$timesTamp,$appId,$appSecret,$this->shop['id']);
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

}