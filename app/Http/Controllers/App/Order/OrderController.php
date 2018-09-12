<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/8/16
 * Time: 13:47
 */

namespace App\Http\Controllers\App\Order;


use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\PayEvent;
use App\Events\SubscribeEvent;
use App\Http\Controllers\App\InitController;
use App\Models\AppContent;
use App\Models\Code;
use App\Models\Column;
use App\Models\Content;
use App\Models\FailContentSyn;
use App\Models\InviteCode;
use App\Models\Member;
use App\Models\Payment;
use App\Models\ShopApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class OrderController extends InitController
{

    /**
     * 接收app端同步过来的订单数据，并入库操作
     */
    public function callback(Request $request){
        $uid = $request['user']['ori_id'];
        $app_content = AppContent::where('app_content_id',$request->product)->select('content_id','content_type')->first();
        if($app_content){
            if($app_content->content_type != 'column'){
                $content = Content::where(['hashid' => $app_content->content_id,'type' => $app_content->content_type])
                    ->select('hashid','type','indexpic','title','shop_id')
                    ->first();
            }else{
                $content = Column::where('hashid',$app_content->content_id)
                    ->select('hashid','indexpic','title','shop_id')
                    ->first();
            }
            $member = Member::where('uid',$uid)
                ->select('uid as user_id','nick_name','avatar','shop_id','amount','id')
                ->first();
            if($content && $member){
                $pay = new Payment();
                $payInfo = $this->formatDataPay($content,$request);
                $pay->setRawAttributes($payInfo);
                $pay->saveOrFail();
                $content_type = isset($content->type) ? $content->type : 'column';
                switch ( $payInfo['content_type']){
                    case 'column':
                    case 'course':
//                        Cache::forever('payment:'.$payInfo['shop_id'].':'.$payInfo['user_id'].':'.$payInfo['content_id'].':'.$payInfo['content_type'],-1);
                        break;
                    default :
//                        Cache::forever('payment:'.$payInfo['shop_id'].':'.$payInfo['user_id'].':'.$payInfo['content_id'],-1);
                        break;
                }
                $payInfo['content_type'] == 'column' && $this->saveContentId($app_content,$member);
                event(new SubscribeEvent($content->hashid,$content_type,$content->shop_id, $member->uid, $pay->payment_type));
                $member->increment('amount',$request->total); //同步消费总额
                return $this->output([
                    'error_code'    => 0,
                    'error_message' => '',
                ]);
            }else{
                return $this->output([
                    'error_code'    => 'no_data',
                    'error_message' => '商品或者会员不存在',
                ]);
            }
        }else{
            $this->validateFailDate(); //将信息存入失败表中
            return $this->output([
                'error_code'    => 'no_content_id',
                'error_message' => trans('validation.no_content_id'),
            ]);
        }
    }



    //拼接payment数据
    private function formatDataPay($content,$request){
        return [
            'user_id'               => $request['user']['ori_id'],
            'nickname'              => $request['user']['username'] ? : '',
            'avatar'                => $request['user']['avatar'] ? : '',
            'payment_type'          => 1,
            'content_id'            => $content->hashid,
            'content_type'          => isset($content->type) ? $content->type : 'column',
            'content_title'         => $content->title ,
            'content_indexpic'      => $content->indexpic,
            'price'                 => $request->total,
            'shop_id'               => $content->shop_id,
            'source'                => 'app',
        ];
    }



    private function saveContentId($app_content,$member){
        $content_id = Column::where(['column.hashid'=>$app_content->content_id,'column.shop_id'=>request('shop_id')])->leftJoin('content','content.column_id','=','column.id')->where('content.payment_type',1)->pluck('content.hashid')->toArray();
        if($content_id){
            Redis::sadd('subscribe:h5:'.request('shop_id').':'.$member->user_id,$content_id);
        }
    }

    private function validateFailDate(){
        FailContentSyn::insert([
            'route'       => app('request')->fullUrl(),
            'shop_id'     => $this->shop['id'],
            'input_data'  => app('request')->all() ? json_encode(['param' => app('request')->all(),'header' => app('request')->header()]) : '',
            'create_time' => hg_format_date(time()),
        ]);


    }

    /**
     * app订单列表
     */
    public function orderList(){
        $this->validateWithAttribute(['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']);
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.orderList'));
        $data = $this->formatData();
        $client = hg_hash_sha1($data,$shop_app->appkey,$shop_app->appsecret);
        try{
            $response = $client->request('GET',$url);
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'app'));
            $this->errorWithText('app-order-error',$exception->getMessage());
        }
        $result = json_decode($response->getBody()->getContents(),1);
        if($result['error_code'] == 0){
            return $result['result'];
        }else{
            $this->errorWithText($response['error_code'],$response['error_message']);
        }
    }

    private function formatData(){
        return [
            'api.page'=>request('api.page')?:1,
            'api.size'=>request('api.size')?:20,
            'order_on'=>request('order_on')?:'',
            'product_name'=>request('product_name')?:'',
            'status'=>request('buyer_name')?:'',
            'buyer_name'=>request('buyer_name')?:'',
            'trade_start_time'=>request('trade_start_time')?:0,
            'trade_end_time'=>request('trade_end_time')?:0,
        ];
    }



}