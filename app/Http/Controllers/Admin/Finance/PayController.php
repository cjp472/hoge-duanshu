<?php
/**
 * 财务管理-开通记录管理
 */

namespace App\Http\Controllers\Admin\Finance;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\PintuanGroupEvent;
use App\Events\PintuanPaymentEvent;
use App\Events\PintuanRefundsEvent;
use App\Events\PintuanRefundsRequestEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Models\AppletUpgrade;
use App\Models\FightGroup;
use App\Models\FightGroupFailed;
use App\Models\FightGroupMember;
use App\Models\Order;
use App\Models\OrderMarketingActivity;
use App\Models\Payment;
use App\Models\RefundOrder;
use App\Models\Shop;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Factory;
use Maatwebsite\Excel\Facades\Excel;

class PayController extends BaseController
{
    public function lists(Request $request)
    {
        $count = $request->count ?: 10;
        $payment = Payment::where('shop_id',$this->shop['id'])->orderby('order_time','desc')->paginate($count);
        $this->transTime($payment->items());
        $ret = $this->listToPage($payment);
        $person = Payment::where('shop_id',$this->shop['id'])->count();
        $ret['statics'] = [
            'person'    => $person ?: 0,
        ];
        return $this->output($ret);
    }

    public function delete($uid,$cid,$type,$ptype)
    {
        return $this->output([
            'success' => true
        ]);
    }


    public function download()
    {
        $data = [
            [
                '订单号',
                '头像',
                '用户ID',
                '昵称',
                '手机号',
                '真实姓名',
                '公司',
                '职位',
                '地址',
                '订单类型',
                '购买类型',
                '订单内容',
                '订单总额(元)',
                '订单时间',
            ],
            [
                'oo_58dc6f0653066_loHjqs5Y',
                'http://wx.qlogo.cn/mmopen/TMWcHgLYzaicpJjSNCCKZEUkiaY7HTniar9xUXM7ibP1A7GmelvojHE9QZpo5ZeVNUL1BjOr6MPGmctoM4ticl8xEvxpTSFgIgh2R/0',
                'u_58da3d1c06de2_JjbGOsAy',
                'Jeffrey',
                '',
                'Jeffrey',
                '',
                '',
                '',
                '直播',
                '现金购买',
                '123123',
                '0.01',
                '2017-03-30 10:35:50'
            ],
            [
                'oo_58dc6f0653066_loHjqs5Y',
                'http://wx.qlogo.cn/mmopen/TMWcHgLYzaicpJjSNCCKZEUkiaY7HTniar9xUXM7ibP1A7GmelvojHE9QZpo5ZeVNUL1BjOr6MPGmctoM4ticl8xEvxpTSFgIgh2R/0',
                'u_58da3d1c06de2_JjbGOsAy',
                'Jeffrey',
                '',
                'Jeffrey',
                '',
                '',
                '',
                '直播',
                '现金购买',
                '123123',
                '0.01',
                '2017-03-30 10:35:50'
            ],
        ];
        Excel::create('2017-03订购数据', function($excel) use($data) {
            $excel->sheet('订单数据', function($sheet) use($data) {
                $sheet->fromArray($data,null,'A2',false,false);
            });
        })->export('xls');
    }

    private function transTime($data)
    {
        foreach ($data as $v){
            $v->order_time && $v->order_time = date('Y-m-d H:i:s',$v->order_time);
        }
    }

    /**
     * 拼团失败，会员退款，由python在拼团失败时调用
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fightGroupCallback(Request $request){

        if(env('APP_ENV') != 'production') {
            $client = new Client([
                'headers' => $request->headers->all(),
                'body' => $request->all() ? json_encode($request->all()) : '',
            ]);
            $url =  'http://api.duanshu.com/server/fight/callback';
            try {
                $return = $client->request('POST', $url);
            } catch (\Exception $exception) {
                event(new ErrorHandle($exception, 'duanshu'));
                $this->error('error-curl');
            }
            $response = json_decode($return->getBody()->getContents(), 1);
            event(new CurlLogsEvent(json_encode($response), $client, $url));
            return response()->json([
                'error' => '',
                'message' => 'success',
            ]);
        }

        $this->validateRefundRequest($request);
        foreach ($request->all() as $item) {
            $fight_group_id = $item['fight_group'];
            //判断拼团组是否存在
            $fight_group = FightGroup::findOrFail($fight_group_id);

            //拼团组处于拼团中不处理
            if ($fight_group->status == 'waiting') {
                continue;
            }
            //只处理退款逻辑
            event(new PintuanRefundsRequestEvent($fight_group_id));

//            //拼团组已删除
//            if ($fight_group->is_del) {
//                $this->error('fight-group-deleted');
//            }
//            //拼团组状态非失败状态
//            if ($fight_group->status != $item['status']) {
//                $this->error('fight-group-status-error');
//            }
            //拼团成功开通权限，失败，退款
//            switch ($item['status']) {
//                case 'complete':
//                    $this->fightGroupComplete($fight_group_id);
//                    break;
//                case 'failed';
//                    event(new PintuanRefundsRequestEvent($fight_group_id));
//                    break;
//                default:
//                    break;
//            }
        }
        return response()->json([
            'error' => '',
            'message' => 'success',
        ]);

    }

    /**
     * 验证请求参数
     * @param $request
     */
    private function validateRefundRequest($request){
        if($request->all() && is_array($request->all())) {

            foreach ($request->all() as $item) {

                $validator = app(Factory::class)->make($item, [
                    'fight_group' => 'required|alpha_dash',
                    'status' => 'required|alpha_dash',
                ], [
                    'fight_group' => '拼团组id',
                    'status' => '拼团组状态',
                ]);
                if ($validator->fails()) {
                    $this->throwValidationException($request, $validator);
                }
            }
        }else{
            $this->error('param-error');
        }
    }

    /**
     * 拼团成功回调处理
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fightGroupCompleteCallback(Request $request){
        $this->validateRefundRequest($request);
        foreach ($request->all() as $item) {
            $fight_group_id = $item['fight_group'];
            //拼团成功开通权限
            switch ($item['status']) {
                case 'complete':
                    $this->fightGroupComplete($fight_group_id);
                    break;
                default:
                    break;
            }
        }
        return response()->json([
            'error'     => '',
            'message'   => 'success',
        ]);
    }

    /**
     * 拼团成功 开通权限
     * @param $fight_group_id
     */
    private function fightGroupComplete($fight_group_id){
        $order_center_no = FightGroupMember::where(['fight_group_id'=>$fight_group_id,'is_creator'=>1])->value('order_no');
        $order = Order::where('center_order_no',$order_center_no)->first();
        $order && event(new PintuanPaymentEvent($fight_group_id,$order));

    }

    /**
     * 拼团失败退款重试
     */
    public function fightGroupRefundRetry(){
        $refund_order_list = RefundOrder::whereIn('refund_status',[0,3])->groupBy('order_no')->limit(1000)->get();
        if($refund_order_list->isNotEmpty()) {
            $lists = $refund_order_list->chunk(20);
            if ($lists->isNotEmpty()) {
                foreach ($lists as $refund_order) {
                    foreach ($refund_order as $item) {
                        //测试代码只处理预发布订单
                        if ($item->order && $item->order->channel != getenv('APP_ENV')) {
//                            continue;
                        }
                        if ($item->wechat_refund_id && $item->order) {
                            $this->appletRefundRequest($item->order,$item->order->center_order_no,$item);
                        } else {
                            //h5退款
                            $param = [
                                'buyer_id' => $item->order ? $item->order->user_id : '',
                                'order_no' => $item->order_id,  //字段待确认
                                'order_item' => '',
                                'quantity' => 1,
                                'refund_type' => 'money',
                                'refund_reason' => '短书拼团失败退款',
                                'auto_refund' => true
                            ];
                            event(new PintuanRefundsEvent($param));

                        }
                    }
                }
            }
        }
        return $this->output(['success'=>1]);


    }

    /**
     * 拼团重试
     */
    public function fightGroupRetry(){

        $fight_group_failed = FightGroupFailed::where('param','!=','')->limit(100)->get();
        if($fight_group_failed->isNotEmpty()) {
            foreach ($fight_group_failed as $item) {
                $order = Order::where(['order_id' => $item->order_id])->first();
                //如果重试了三次之后仍然不成功，进行退款
                if ($order) {
//                    if($item->try_times > 3) {
                    if ($order->source == 'applet') {
                        $this->appletRefunds($order);
                    } else {
                        $param = [
                            'buyer_id' => $order->user_id,
                            'order_no' => $order->order_id,  //字段待确认
                            'order_item' => '',
                            'quantity' => 1,
                            'refund_type' => 'money',
                            'refund_reason' => '短书拼团失败退款',
                            'auto_refund' => true
                        ];
                        event(new PintuanRefundsEvent($param));
                    }
                    //删除已退款的记录
                    $item->delete();

//                    } else {
//                        event(new PintuanGroupEvent(unserialize($item->param), $order));
                }
            }
        }
    }

    /**
     * 小程序退款
     */
    private function appletRefunds($order){

        $order_no = $order->center_order_no;
        $retry_status = [1,3];
        $refund_order = RefundOrder::where(['order_no'=>$order->center_order_no])->first();
        if(!$refund_order){
            $refund_order = new RefundOrder();
            $refund_no = date('ymdHis').str_pad(time()+mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

            $refunds_param = [
                'order_no'  => $order_no,
                'order_id'  => $order->order_id,
                'shop_id'  => $order->shop_id,
                'refund_no' => $refund_no,
            ];
            $refund_order->setRawAttributes($refunds_param);
            $refund_order->save();
        }elseif ($refund_order && !in_array($refund_order->refund_status,$retry_status)){
            //订单退款中或者已经退款不再发起申请退款请求
            return false;
        }
        $this->appletRefundRequest($order,$order_no,$refund_order);
        return $this->output(['success'=>1]);


    }

    private function appletRefundRequest($order,$order_no,$refund_order){
        //先通知订单中心
        $param = [
            'buyer_id' => $order->user_id,
            'order_no' => $order->order_id,  //字段待确认
            'order_item' => '',
            'quantity' => 1,
            'refund_type' => 'money',
            'refund_reason' => '短书拼团失败退款-小程序端',
        ];
        event(new PintuanRefundsEvent($param));


        $param = [
            'order_no'      => $order_no,
            'refund_no'     => $refund_order->refund_no,
            'total_fee'     => round($order->price*100),
            'refund_fee'    => round($order->price*100),
            'mch_id'        => AppletUpgrade::where(['shop_id'=>$order->shop_id])->value('mchid')?:'',
            'shop_id'       => $order->shop_id,
        ];
        $return = hg_applet_refunds($param,'out_trade_no');
        //退款申请提交成功，
        if($return && $return['return_code'] == 'SUCCESS' && $return['result_code'] == 'SUCCESS'){
            //退款中
            $refund_order->refund_status = 1;
            $refund_order->wechat_refund_id = $return['refund_id'];
            $refund_order->save();
            //订单状态修改,退款中，
            $order->pay_status = -3;
            $order->save();
        }else{
            //退款申请失败，后期可以根据此状态进行重试
            $refund_order->refund_status = 3;
            $refund_order->save();
            //退款失败，
            $order->pay_status = -5;
            $order->save();
        }
    }

    /**
     * 拼团失败订单数据处理脚本
     */
    public function fightGroupOldFailedOrder(){

        //小程序申请失败
        if(request('type') == 1) {
            $refund_order = RefundOrder::where('refund_status', 0)
                ->where('order_center_refund_id', '!=', '')
                ->where('wechat_refund_id', '=', '')
                ->pluck('order_id')->toArray();
            $order_lists = Order::whereIn('order_id', $refund_order)
                ->get();
        }elseif (request('order_no')){
            $order_lists = Order::whereIn('center_order_no',explode(',',request('order_no')))->get();
        }else{
            $order_lists = Order::leftJoin('fightgroupmember as fg','fg.order_no','=','order.order_id')
                ->where('order_type',3)
                ->where('pay_status',-6)
                ->whereNull('fg.order_no')
                ->select('order.*')
                ->get();
        }

        foreach ($order_lists as $order) {

            if ($order->source == 'applet') {
                $this->appletRefunds($order);
            } else {
                $param = [
                    'buyer_id' => $order->user_id,
                    'order_no' => $order->order_id,  //字段待确认
                    'order_item' => '',
                    'quantity' => 1,
                    'refund_type' => 'money',
                    'refund_reason' => '短书拼团失败退款',
                    'auto_refund' => true
                ];
                event(new PintuanRefundsEvent($param));
            }
        }
        return $this->output(['success'=>1]);



    }

}