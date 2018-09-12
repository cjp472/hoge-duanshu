<?php
/**
 * 订单生成处理
 */
namespace App\Http\Controllers\H5\Finance;

use App\Events\AdmireOrderEvent;
use App\Http\Controllers\H5\BaseController;
use App\Jobs\CheckAdmireOrderPayment;
use App\Models\AdmireOrder;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Http\Request;


/**
 * 只支持直播内容
 */
class AdmireController extends BaseController
{

    /**
     * 赞赏订单
     * @param Request $request
     * @return mixed
     */
    public function admireOrder(Request $request)
    {
        $this->validateOrder();
        $order = new AdmireOrder();
        $order->setRawAttributes($this->formatOrder($request));
        $order->order_id = $this->generateOrderId(time());
        $order->saveOrFail();
        $job = (new CheckAdmireOrderPayment($order))->onQueue(DEFAULT_QUEUE)
            ->delay(Carbon::now()->addMinutes(30));
        dispatch($job);
        event(new AdmireOrderEvent($order)); //同步赞赏订单到订单中心
        return $this->output($this->getResponse($request,$order));

    }

    /**
     * 小程序赞赏
     *
     * @return void
     */
    public function appletAdmireOrder(Request $request){
        $this->validateOrder();
        $order = new AdmireOrder();
        $order->setRawAttributes($this->formatOrder($request));
        $order->order_id = $this->generateOrderId(time());

        event(new AdmireOrderEvent($order)); //同步赞赏订单到订单中心
        
        $order->saveOrFail();
        $job = (new CheckAdmireOrderPayment($order))->onQueue(DEFAULT_QUEUE)
            ->delay(Carbon::now()->addMinutes(30));
        dispatch($job);
        
        $wxOrder = [
            "body"=>'赞赏'.$request->input('lecturer_name','讲师'),
            "detail"=>'赞赏'.$request->input('lecturer_name','讲师'),
            "out_trade_no"=>$order->center_order_no,
            "notify_url"=>config('define.pay.applet.admire_url'),
            "price"=>$order->price,
            "attach"=>[]
        ];
        $wxPayInfo = hg_applet_wx_pre_order($this->shop['id'], request('openid') ? : $this->member['openid'], $wxOrder);
        $return = $this->wxPaySign($wxPayInfo);
        return $this->output($return);
    }

    private function wxPaySign($pay_info){
        $payment = [
            'appId'     => $pay_info['return']->appid,
            'nonceStr'  => $pay_info['return']->nonce_str,
            'package'   => 'prepay_id='.$pay_info['return']->prepay_id,
            'signType'  => 'MD5',
            'timeStamp' => time(),
        ];
        $param = ['key' => $pay_info['key']];
        $string = '';
        if ($payment && $param) {
            foreach (array_merge($payment, $param) as $k=>$v) {
                $string .= $k.'='.$v.'&';
            }
        }
        $pay_sign = strtoupper(md5(trim($string, '&')));
        $payment['paySign'] = $pay_sign;
        return $payment;

    }

    private function validateOrder()
    {
        $this->validateWithAttribute(
            [
                'content_id'    => 'required|max:64',
                'lecturer'      => 'required',
                'lecturer_name' => 'required',
                'money'         => 'required|numeric'
            ],[
                'content_id'    => '直播id',
                'lecturer'      => '讲师id',
                'lecturer_name' => '讲师昵称',
                'money'         => '赞赏金额',
            ]
        );
    }

    private function formatOrder(Request $request)
    {
        return [
            'shop_id'       => $this->shop['id'],
            'user_id'       => $this->member['id'],
            'nickname' => $this->member['nick_name'] ? : $this->member['openid'],
            'avatar'        => $this->member['avatar'],
            'content_id'    => $request->content_id,
            'content_type'  => 'live',
            'lecturer'      => $request->lecturer,
            'lecturer_name' => $request->lecturer_name,
            'pay_status'    => 0, //待支付
            'order_type'    => $request->order_type ?: 3, //赞赏
            'order_time'    => time(),
            'price'         => round($request->money,2),
            'channel'       => env('APP_ENV')=='production'?'production':'pre',
        ];
    }

    private function generateOrderId($cid)
    {
        return date('ymdHis').str_pad($cid+mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
    }

    private function getResponse(Request $request,AdmireOrder $order)
    {
        $pay = [
            'out_biz_no'    => $order->center_order_no,
            'pay_amount'    => round($order->price * 100),
            'mch_id'        => $request->mch_id?:0,
            'mch_name'      => $request->mch_name?:Shop::where(['hashid'=>$this->shop['id']])->value('title'),
//            'pay_tool'      => $request->pay_tool ?: 'WX_JS',//'WX_JS','ALIPAY_WAP'
            'trade_desc'    => '短书支付',
            'goods_desc'    => '短书直播赞赏',
            'goods_name'    => '短书直播赞赏：'.($order->content ? $order->content->title : ''),
            'memo'          => $request->memo ?: '短书支付',
            'payer_id'      => $this->member['id'],
            'payer_name'    => $this->member['nick_name'],
            'app_id'        => config('define.pay.youzan.app_id'),
            'app_type'      => $request->app_type?:'WAP',
            'return_url'    => $request->return_url ?: config('define.pay.plat.return_url'),
            'notify_url'    => config('define.pay.plat.admire_url'),
        ];
        $data = $this->setSignature($pay);
        return ['param'=> urlencode(base64_encode(json_encode($data)))];
    }


    private function setSignature($pay = [])
    {
        $timestamp = time();
        $pay['timestamp'] = $timestamp;
        $pay['nonce'] = str_random(12);
        $param = [
            'app_secret' => config('define.pay.youzan.app_secret'),
        ];
        $sign_param = array_merge($pay,$param);
        ksort($sign_param);
        $pay['sign'] = hg_hash_sha256($sign_param);
        return $pay;
    }
}