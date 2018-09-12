<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/11/22
 * Time: 08:54
 */

namespace App\Http\Controllers\H5\Finance;


use App\Events\CurlLogsEvent;
use App\Events\OrderMakeEvent;
use App\Http\Controllers\H5\BaseController;
use App\Jobs\CheckOrderPayment;
use App\Models\Alive;
use App\Models\AppletUpgrade;
use App\Models\CardRecord;
use App\Models\Column;
use App\Models\Community;
use App\Models\Content;
use App\Models\Course;
use App\Models\LimitPurchase;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Order;
use App\Models\Shop;
use App\Models\FightGroupActivity;
use App\Models\OrderMarketingActivity;
use Carbon\Carbon;
use EasyWeChat\Foundation\Application;
use function EasyWeChat\Payment\get_client_ip;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;


class AppletController extends BaseController
{

    /**
     * 小程序下单接口
     */
    public function orderMake(Request $request){
        $this->validateOrder($request);
        $dbmember = Member::where('uid',$this->member['id'])->first();
        //获取内容信息
        $content = $this->getContentInfo($request);

        //下订单
        $order = $this->makeOrder($request,$content, $dbmember);
        //设置订单超时事件
        $job = (new CheckOrderPayment($order))->onQueue(DEFAULT_QUEUE)
            ->delay(Carbon::now()->addMinutes(15));
        dispatch($job);
        //微信预下单
        $pay_info = $this->makePreOrder($order);

        //如果是拼团订单，记录团与会员的关联关系
        if($request->order_type == 3 && $request->group_id){
            $group_member = [
                'member_id' => $this->member['id'],
                'group_id'  => $request->group_id,
            ];
            Cache::forever('fight:group:member:'.$order->center_order_no,json_encode($group_member));
        }

        if($request->fight_group_activity_id){
            $order_marketing_param = [
                'order_no'  => $order->center_order_no,
                'order_id'  => $order->order_id,
                'marketing_activity_id'  => $request->fight_group_activity_id ? : '',
            ];
            OrderMarketingActivity::insert($order_marketing_param);
        }
        $response = $this->formatPaymentData($pay_info);

        return $this->output($response);
    }

    private function validateOrder($request)
    {
        $this->validateWith(
            [
                'content_id'    => 'required',
                'content_type'  => 'required',
                'number' => 'numeric',
            ]
        );
        //拼团订单验证拼团活动信息
        if(request('order_type') == 3){
//            $this->check_payment(request('content_type'),request('content_id'));
            $this->validatePintuan();
        }

        if($request->content_type == 'member_card') {
            $mc = MemberCard::where(['hashid'=>$request->content_id,'shop_id'=>$this->shop['id']])->firstOrFail();
            is_null($request->membercard_option) && $request->membercard_option = 0;
            $request->membercard_option = intVal($request->membercard_option);
            if (!$mc->validOption($request->membercard_option)) {
                $this->error('error-membercard-option');
            }
        }

    }

    private function formatPaymentData($pay_info){
        $payment = [
            'appId'     => $pay_info['return']->appid,
            'nonceStr'  => $pay_info['return']->nonce_str,
            'package'   => 'prepay_id='.$pay_info['return']->prepay_id,
            'signType'  => 'MD5',
            'timeStamp' => time(),
        ];
        $param = ['key' => $pay_info['key']];
        $string = '';
        if($payment && $param){
            foreach (array_merge($payment,$param) as $k=>$v){
                $string .= $k.'='.$v.'&';
            }
        }
        $pay_sign = strtoupper(md5(trim($string,'&')));
        $payment['paySign'] = $pay_sign;
        return $payment;
    }


    private function processMemberRecord($request){
        $record = CardRecord::where(['member_id'=>$this->member['id'],'card_id'=>$request->content_id])->where('end_time','>',time())->first();
        $record && $this->error('card_already_buy');
    }

    /**
     * 获取购买的内容的信息（课程，专栏，单篇内容）
     */
    private function getContentInfo(Request $request){
        if($request->content_type=='column'||$request->content_type=='course'){
            $key = 'content:'.$this->shop['id'].':'.$request->content_type.':'.$request->content_id;
            // $content = json_decode(Cache::get($key));
             $content = null;
        }elseif($request->content_type == 'member_card'){
            $request->order_type != 2 && $this->processMemberRecord($request);
            // $key = 'member:card:'.$this->shop['id'].':'.$request->content_id;
            $content = null;
        }elseif($request->content_type == 'community'){
            $key = 'community:'.$this->shop['id'].':'.$request->content_id;
            // $content = json_decode(Cache::get($key));
             $content = null;
        }else{
            $key = 'content:'.$this->shop['id'].':'.$request->content_id;
            // $content = json_decode(Cache::get($key));
            $content = null;
        }
        if(!$content){
            switch ($request->content_type){
                case 'column':
                    $content = Column::where(['hashid'=>$request->content_id,'shop_id'=>$this->shop['id']])->firstOrFail();
                    $content->makeVisible(['hashid']);
                    Cache::forever('content:'.$this->shop['id'].':'.$request->content_type.':'.$request->content_id,json_encode($content->toArray()));
                    $content->type = 'column';
                    $indexpic = hg_unserialize_image_link($content->indexpic);
                    $content->indexpic = $indexpic ? $indexpic['host'].$indexpic['file']:'';
                    break;
                case 'course':
                    $content = Course::where(['hashid'=>$request->content_id,'shop_id'=>$this->shop['id']])->firstOrFail();
                    $content->makeVisible(['hashid']);
                    Cache::forever('content:'.$this->shop['id'].':'.$request->content_type.':'.$request->content_id,json_encode($content->toArray()));
                    $content->type = 'course';
                    $indexpic = hg_unserialize_image_link($content->indexpic);
                    $content->indexpic = $indexpic ? $indexpic['host'].$indexpic['file']:'';
                    break;
                case 'member_card':
                    $content = MemberCard::where(['hashid'=>$request->content_id,'shop_id'=>$this->shop['id']])->firstOrFail();
                    $content->makeVisible(['hashid']);
                    $content->title = $content->nameAtBuy($request->membercard_option);
                    $content->type = 'member_card';
                    $content->price = $content->optionPrice($request->membercard_option);
                    $indexpic = MemberCard::STYLEINDEXPIC[$content->style] ? MemberCard::STYLEINDEXPIC[$content->style] : MemberCard::INDEXPIC;
                    $content->indexpic = $indexpic['host'].$indexpic['file'];
                    break;
                case 'community':
                    $content = Community::where(['hashid'=>$request->content_id,'shop_id'=>$this->shop['id']])->firstOrFail();
                    $content->makeVisible(['hashid']);
                    Cache::forever('community:'.$this->shop['id'].':'.$request->content_id,json_encode($content->toArray()));
                    $content->type = 'community';
                    $indexpic = hg_unserialize_image_link($content->indexpic);
                    $content->indexpic = $indexpic ? $indexpic['host'].$indexpic['file']:'';
                    break;
                default:
                    $content = Content::where(['hashid'=>$request->content_id,'shop_id'=>$this->shop['id']])->firstOrFail();
                    $content->makeVisible(['hashid']);
                    Cache::forever('content:'.$this->shop['id'].':'.$request->content_id,json_encode($content->toArray()));
                    $indexpic = hg_unserialize_image_link($content->indexpic);
                    $content->indexpic = $indexpic ? $indexpic['host'].$indexpic['file']:'';
                    break;
            }
        }
        if($content && isset($content->payment_type) && $content->payment_type == 1){
            $this->error('no-support-buy');
        }
        return $content;
    }

    /**
     * 保存订单信息到短书数据库，如果已经存在的有效未支付订单，直接返回订单信息
     * @param Request $request
     * @param $content
     * @return Order|\Illuminate\Database\Eloquent\Model|null|static
     */
    private function makeOrder(Request $request,$content, $dbmember)
    {
        $channel = env('APP_ENV')=='production'?'production':'pre';
        $order = Order::where(['user_id' => $this->member['id'], 'content_id' => $request->content_id, 'content_type' => $request->content_type, 'order_type' => $request->order_type ?: 1, 'pay_status' => 0, 'channel' =>$channel])->first();
        $order_price = $this->orderPrice($request, $content);
        //验证价格是否为0，价格为0 不需要进行下单支付
        if(empty((float)$order_price)){
            $this->error('free-content');
        }
        $order_type = [3,4];    //订单类型,3-拼团，4-团购
        if (!$order || !$order->center_order_no || $order->price != $order_price || in_array($request->order_type,$order_type)) {
            $order = new Order();
            $param = [
                'user_id' => $this->member['id'],
                'nickname' => $dbmember && $dbmember->nick_name ? $dbmember->nick_name: $this->member['openid'],
                'avatar' => $dbmember ? $dbmember->avatar : '',
                'content_id' => $content->hashid,
                'pay_status' => 0, //待支付
                'order_type' => $request->order_type ?: 1, //普通
                'order_time' => time(),
                'content_type' => $request->content_type,
                'content_title' => $content->title,
                'price' => $order_price,
                'content_indexpic' => $content->indexpic,
                'shop_id' => $this->shop['id'],
                'number' => $request->number ?: 1,
                'source' => 'applet',
                'channel'   => $channel,
                'content_price' => $content->price
            ];
            $order->setRawAttributes($param);
            if($request->content_type == 'member_card') { // 保存选择的会员卡规格
                $order->orderMemberCard($content, $request->membercard_option);
            }
            $order->order_id = $this->generateOrderId($content->id);
            $order->saveOrFail();
            $limit_id = Redis::get('purchase:'.$this->shop['id'].':'.$request->content_type.':'.$request->content_id);
            $limit_id && Redis::sadd('limit:purchase:'.$this->shop['id'].':'.$limit_id,$order->order_id);
        }else{
            $order->price = $order_price;
            $order->content_price = $content->price;
            $order->content_title = $content->title;
            $order->content_indexpic = $content->indexpic;
            if($request->content_type == 'member_card') { // 保存选择的会员卡规格
                $order->orderMemberCard($content, $request->membercard_option);
            }
            $order->save();
        }
        if (!$order->center_order_no) {
            event(new OrderMakeEvent($order, $content,$request)); //同步订单到订单中心
        }
        return $order;
    }

    /**
     * 请求微信预下单
     */
    private function makePreOrder($orders){
        return hg_applet_undefined_order($orders,$this->shop['id'],request('openid') ? : $this->member['openid']);
    }

    /**
     * 处理订单价格
     * @param $request
     * @param $content
     * @return float
     */
    private function orderPrice($request,$content){
        $order_price = $content->price * (isset($request->number) && $request->number >= 1 ? intval($request->number): 1);
        if($request->content_type =='member_card' || $request->order_type == 2){
        }elseif ($request->order_type == 3) {//拼团订单
            $fight_group_activity = FightGroupActivity::where(['product_identifier' => request('content_id'), 'product_category' => request('content_type'),'is_del'=>0])->orderByDesc('end_time')->first(['origin_price', 'now_price']);
            $fight_price = $fight_group_activity ? round($fight_group_activity->now_price / 100, 2) : 0.00;
            $content->promotion_price = $fight_price;
            $order_price = $fight_price<0? 0.00 : $fight_price;
        }else{ //会员卡折扣不对会员卡和赠送购买生效
            $price = $this->getDiscountPrice($content->price,$request->content_id,$request->content_type,boolVal($content->join_membercard));
            $order_price = $price * (isset($request->number) && $request->number >= 1 ? intval($request->number): 1);
        }

        if($order_price > MAX_ORDER_PRICE){
            $this->error('max-order-price-error');
        }

        return $order_price;
    }

    /**
     * @param $cid
     * @return string
     * 生成随机订单号
     */
    private function generateOrderId($cid)
    {
        return date('ymdHis').str_pad($cid+mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
    }


    private function check_payment($type,$id){
        if($type == 'column'){ //专栏
            if($this->checkColumnPay($id,$type)){
                $this->error('goods_already_buy');
            }
        }else if($type == 'course'){
            if($this->checkCoursePay($id,$type)){
                $this->error('goods_already_buy');
            }
        } else {
            $content = json_decode(Cache::get('content:'.$this->shop['id'].':'.$id));
            if(!$content || !isset($content->is_test)){
                $content = Content::where(['hashid'=>$id, 'type'=>$type, 'shop_id'=>$this->shop['id']])->firstOrFail();
                $content->makeVisible(['hashid']);
                Cache::forever('content:'.$this->shop['id'].':'.$id,json_encode($content->toArray()));
            }
            switch (intval($content->payment_type)){
                //专栏调整，兼容老数据专栏外单卖和专栏相同判断处理，
                case 1: //专栏
                case 4: //专栏外单卖
                    if ($content->column_id && $this->checkColumnPayment($content->column_id)){
                        $this->error('goods_already_buy');
                    }
                    break;
                case 2: //收费
                    if($this->checkPayment($id,$type)){
                        $this->error('goods_already_buy');
                    }
                    break;
                case 3: //免费
                    break;
                default :
                    if($this->checkPayment($id,$type)){
                        $this->error('goods_already_buy');
                    }
                    break;
            }
        }
    }

    private function checkColumnPayment($column_id)
    {
        $column = Column::find($column_id);
        return $this->checkProductPayment('column',$column->hashid);
    }

    private function checkCoursePay($course_id,$type)
    {
        $content = json_decode(Cache::get('content:'.$this->shop['id'].':course:'.$course_id));
        if(!$content){
            $content = Course::where(['hashid'=>$course_id,'shop_id'=>$this->shop['id']])->firstOrFail();
            $content->makeVisible(['hashid']);
            Cache::forever('content:'.$this->shop['id'].':course:'.$course_id,json_encode($content->toArray()));
        }
        switch (intval($content->pay_type)){
            case 1: //收费
                if($this->checkCoursePayment($course_id,$type)){
                    return 1;
                }
                break;
            default :
                return 1;
                break;
        }
    }

    private function checkCoursePayment($course_id,$type)
    {
        return $this->checkProductPayment($type,$course_id);
    }


    private function checkColumnPay($column_id,$type)
    {
        return $this->checkProductPayment($type,$column_id);
    }

    private function checkPayment($id,$type)
    {
        $pay = $this->checkProductPayment($type,$id);
        $type=='live' && $lecturer = $this->checkLecturer($id);
        if($pay || $lecturer){
            return 1;
        }else{
            return 0;
        }
    }

    private function checkLecturer($id){
        $alive = Alive::where('content_id',$id)->firstOrFail();
        $person_id = array_pluck(json_decode($alive->live_person, true),'id');
        $lecturer = in_array($this->member['id'],$person_id) ? 1 : 0;
        return $lecturer;
    }


}