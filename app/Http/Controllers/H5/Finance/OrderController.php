<?php
/**
 * 订单生成处理
 */
namespace App\Http\Controllers\H5\Finance;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\OrderMakeEvent;
use App\Events\PromoterRecordEvent;
use App\Http\Controllers\H5\BaseController;
use App\Jobs\CheckOrderPayment;
use App\Models\Alive;
use App\Models\AppletUpgrade;
use App\Models\CardRecord;
use App\Models\Code;
use App\Models\Community;
use App\Models\Content;
use App\Models\Column;
use App\Models\Course;
use App\Models\FightGroup;
use App\Models\FightGroupActivity;
use App\Models\FightGroupMember;
use App\Models\InviteCode;
use App\Models\LimitPurchase;
use App\Models\Member;
use App\Models\MemberBindPromoter;
use App\Models\MemberCard;
use App\Models\Order;
use App\Models\OrderMarketingActivity;
use App\Models\Payment;
use App\Models\PromotionRecord;
use App\Models\PromotionShop;
use App\Models\Shop;
use Carbon\Carbon;
use EasyWeChat\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;
use App\Models\Promotion;
use App\Models\PromotionContent;

class OrderController extends BaseController
{
    /**
     * 我的购买记录
     * @param Request $request
     * @return mixed
     */
    public function lists(Request $request)
    {

        $this->validateWithAttribute([
//            'pay_status'    => 'numeric',
        ],[
            'pay_status'    => '支付状态',
        ]);
        $count = $request->count ?: 10;
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids) {
            $sql = Order::where('shop_id',$this->shop['id'])->whereIn('user_id',$member_ids);
        }else{
            $sql = Order::where(['shop_id'=> $this->shop['id'], 'user_id'=> $this->member['id']]);
        }
        $channel = env('APP_ENV')=='production'?'production':'pre';
        $sql->where('channel',$channel);
        $request->has('pay_status') && $sql->whereIn('pay_status',explode(',',$request->pay_status));
        $order = $sql->select('id','content_id','content_title','content_type','content_indexpic','order_time','price','pay_status','number','center_order_no as order_id','order_type','order_id as order_no', 'content_price')
            ->orderByDesc('order_time')
            ->paginate($count);
        if($order->total() > 0){
            foreach ($order->items() as $v){
                if($v->content_type == 'column'){
                    $v->course = $v->course ? : [];
                }
                switch ($v->content_type){
                    case 'community':
                        if(is_null($v->content_price)) {
                            $v->content_price = $v->community ? $v->community->price : 0;
                        }
                        break;
                    case 'course':
                        if(is_null($v->content_price)) {
                            $v->content_price = $v->course ? $v->course->price : 0;
                        }
                        $v->course_type = $v->course ? $v->course->course_type : 'article';
                        break;
                    case 'member_card':
                        if(is_null($v->content_price)) {
                            $extraData = $v->getExtraData();
                            if($extraData) {
                                $v->content_price = $extraData['membercard_option']['price'];
                            } else {
                                $v->content_price = $v->memberCard ? $v->memberCard->price : 0;
                            }
                        }
                        break;
                    default:
                        if(is_null($v->content_price)) {
                            $v->content_price = $v->belongsToContent ? $v->belongsToContent->price : 0;
                        }
                        break;
                }
                switch ($v->order_type){
                    //拼团
                    case 3:
                        $fight_group_member = FightGroupMember::where(['order_no'=>$v->order_no])->first();
                        $fight_group_activity_id = OrderMarketingActivity::where(['order_no'=>$v->order_id,'marketing_activity_type'=>'fight_group'])->value('marketing_activity_id');
                        if($fight_group_member){
                            $fight_group = FightGroup::find($fight_group_member->fight_group_id);
                            $fight_group_activity = FightGroupActivity::find($fight_group_activity_id);
                            $v->marketing_status = $fight_group ? $fight_group->status : 'failed';
                            $v->has_joined = $fight_group_member->join_success;
                            $fight_group && $fight_group->create_time = strtotime('+8 hour',strtotime($fight_group->create_time));
                            $fight_group_activity && $fight_group_activity->end_time = strtotime('+8 hour',strtotime($fight_group_activity->end_time));
                            $v->marketing_create_time = $fight_group ? hg_format_date($fight_group->create_time) : '';
                            $v->marketing_activity_end_time = $fight_group_activity ? hg_format_date($fight_group_activity->end_time) : '';
                            $v->fight_group_id = $fight_group_member->fight_group_id ? : '';
                            $v->fight_group_activity_id = $fight_group->fight_group_activity_id ? : '';
                        }else{
                            $v->marketing_status = 'failed';
                            $v->fight_group_activity_id = $fight_group_activity_id ? : '';
                        }
                        break;
                    //团购
                    case 4:
                        break;
                    default:
                        break;

                }
                $v->order_time = $v->order_time ? hg_format_date($v->order_time) : '';
                $v->content_indexpic = hg_unserialize_image_link($v->content_indexpic);
            }
        }
        return $this->output($this->listToPage($order));

//        $count = $request->count ?: 10;
////        $mobile = Member::where('uid',$this->member['id'])->value('mobile');
////        $member_ids = Redis::smembers('mobileBind:'.$this->shop['id'].':'.$mobile);
//        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
//        if($member_ids) {
//            $sql = Payment::where('shop_id',$this->shop['id'])->whereIn('user_id',$member_ids);
//        }else{
//            $sql = Payment::where(['shop_id'=> $this->shop['id'], 'user_id'=> $this->member['id']]);
//        }
//        //免费订阅不显示
//        $order = $sql->where('payment_type','!=',4)->select('order_id','content_id','content_title','content_type','content_indexpic','order_time','price','payment_type')->orderby('id','desc')->paginate($count);
//        if($order->total() > 0){
//            foreach ($order->items() as $v){
//                if($v->content_type == 'course'){
//                    $v->course = $v->course ? : [];
//                }
//                $v->order_time = $v->order_time ? hg_format_date($v->order_time) : '';
//                $v->payment_type = $v->payment_type == 1 ? 1 : 2;
//                $v->content_indexpic = hg_unserialize_image_link($v->content_type=='member_card'?config('define.default_card'):$v->content_indexpic);
//            }
//        }
//        return $this->output($this->listToPage($order));
    }

    /**
     * 预生成订单
     * @param Request $request
     * @return mixed
     */
    public function makeOrder(Request $request)
    {
        $this->validateOrder($request);
        $dbmember = Member::where('uid',$this->member['id'])->first();
        switch ($request->content_type){
            case 'column':
            case 'course':
                $key = 'content:'.$this->shop['id'].':'.$request->content_type.':'.$request->content_id;
                // $content = json_decode(Cache::get($key));
                $content = null;
                break;
            case 'member_card':
                $request->order_type != 2 && $this->processMemberRecord($request); // 多规格会员卡，不使用缓存
                // $key = 'member:card:'.$this->shop['id'].':'.$request->content_id;
                $content = null;
                break;
            case 'community':
                $key = 'community:'.$this->shop['id'].':'.$request->content_id;
                // $content = json_decode(Cache::get($key));
                $content = null;
                break;
            default:
                $key = 'content:'.$this->shop['id'].':'.$request->content_id;
                // $content = json_decode(Cache::get($key));
                $content = null;
        }
        
        if(!$content){
            $content = $this->get_content_info($request);
        }

        if($content && isset($content->payment_type) && $content->payment_type == 1){
            $this->error('no-support-buy');
        }

        $where = [
          'user_id'=>$this->member['id'],
          'shop_id'=>$this->shop['id'],
          'content_id'=>$request->content_id,
          'content_type'=>$request->content_type,
          'pay_status'=>0,
          'order_type'=>1,
        ];
        $where['channel'] = env('APP_ENV')=='production'?'production':'pre';
        $order = Order::where($where)->orderBy('order_time','desc')->first();
        $order_price = $this->orderPrice($request, $content);//订单价格处理
        $order_type = [3,4];    //订单类型,3-拼团，4-团购
        if(!$order || $order->price != $order_price || in_array($request->order_type,$order_type)){
            $order = new Order();
            $order->setRawAttributes($this->formatOrder($request, $content, $dbmember));
            $order->order_id = $this->generateOrderId($content->id);
            if($request->content_type == 'member_card') { // 保存选择的会员卡规格
                $order->orderMemberCard($content, $request->membercard_option);
            }
            $order->saveOrFail();
            $job = (new CheckOrderPayment($order))->onQueue(DEFAULT_QUEUE)
                ->delay(Carbon::now()->addMinutes(15));
            dispatch($job);
            $limit_id = Redis::get('purchase:'.$this->shop['id'].':'.$request->content_type.':'.$request->content_id);
            $limit_id && Redis::sadd('limit:purchase:'.$this->shop['id'].':'.$limit_id,$order->order_id);
        }else{//更新内容信息以及订单信息
            $order->price = $order_price;
            $order->content_price = $content->price;
            $order->content_title = $content->title;
            $order->content_indexpic = $content->indexpic;
            if($request->content_type == 'member_card') { // 保存选择的会员卡规格
                $order->orderMemberCard($content, $request->membercard_option);
            }
            $order->save();
        }
        //同步推广记录
        $this->promotion($order, $content);
        if(!$order->center_order_no){
            event(new OrderMakeEvent($order, $content,$request)); //同步订单到订单中心
        }
        //同步到推广记录表
//        if($request->promoter_id){
//            $this->syncPromoter($request,$order);
//        }
        if(!$order->center_order_no){
            $this->error('make-order-error');
        }
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
                'marketing_activity_id'  => $request->fight_group_activity_id? :'',
            ];
            OrderMarketingActivity::insert($order_marketing_param);
        }
        return $this->output(['param' => urlencode(base64_encode(json_encode($this->getResponse($request, $order))))]);
    }

    private function processMemberRecord($request){
        $record = CardRecord::where(['member_id'=>$this->member['id'],'card_id'=>$request->content_id])->where('end_time','>',time())->first();
        $record && $this->error('card_already_buy');
    }


    public function get_content_info(Request $request){
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
        return $content;
    }

    public function goPay($id,Request $request)
    {
        $order = Order::where('order_id',$id)->firstOrFail();
        if($order->pay_status == -1){
            $this->error('order-timeout');
        }
        if($order->pay_status > 0){
            $this->error('order-payed');
        }
        return $this->output($this->getResponse($request,$order));
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
        if($request->promoter_id){
            $promoterStatus = hg_check_promotion($request->promoter_id,$this->shop['id']);
            if ($promoterStatus) {
                return true;
            }
            //不是推广员时推广员信息置空
            request()->offsetUnset('promoter_id');
        }
        //拼团订单验证拼团活动信息
        if($request->order_type == 3){
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
                if($this->checkCoursePayment($type,$course_id)){
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

    private function checkPayment($id, $type)
    {
        $lecturer = 0;
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



    private function formatOrder(Request $request,$content, $dbmember)
    {
        $order_price = $this->orderPrice($request,$content);
        return [
            'user_id' => $this->member['id'],
            'nickname' => $dbmember && $dbmember->nick_name ? $dbmember->nick_name: $this->member['openid'],
            'avatar' => $dbmember ? $dbmember->avatar : '',
            'content_id'=> $content->hashid,
            'pay_status'=> 0, //待支付
            'order_type'=> $request->order_type ?: 1, //普通
            'order_time'    => time(),
            'content_type'  => $request->content_type?:'course',
            'content_title'  => $content->title,
            'price'         => $order_price,
            'content_indexpic'  => $content->indexpic,
            'shop_id'       => $this->shop['id'],
            'number'        => $request->number ?: 1,
            'channel'   => env('APP_ENV')=='production'?'production':'pre',
            'content_price' => $content->price
        ];
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
            $order_price = $fight_price<=0? 0.00 : ($fight_price<0.01 ? 0.01 : $fight_price);
        }else { //会员卡折扣不对会员卡和赠送购买生效
            $price = $this->getDiscountPrice($content->price,$request->content_id,$request->content_type,boolVal($content->join_membercard));
            $order_price = $price * (isset($request->number) && $request->number >= 1 ? intval($request->number): 1);
        }
        if($order_price > MAX_ORDER_PRICE){
            $this->error('max-order-price-error');
        }
        return $order_price;
    }

    private function generateOrderId($cid)
    {
        return date('ymdHis').str_pad($cid+mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
    }

    private function getResponse(Request $request,Order $order)
    {
        $pay = [
            'out_biz_no'    => $order->center_order_no,
            'pay_amount'    => round($order->price * 100),
            'mch_id'        => $request->mch_id,
            'mch_name'      => Shop::where(['hashid'=>$this->shop['id']])->value('title') ?:'',
//            'pay_tool'      => $request->pay_tool ?: 'WX_JS',//'WX_JS','ALIPAY_WAP'
            'trade_desc'    => '短书支付',
            'goods_desc'    => $order->content_type == 'column' ? '订阅:'.$order->content_title : '购买:'.$order->content_title,
            'goods_name'    => $order->content_type == 'column' ? '订阅:'.$order->content_title : '购买:'.$order->content_title,
            'memo'          => $request->memo ?: '短书支付',
            'payer_id'      => $this->member['id'],
            'payer_name'    => $this->member['nick_name'],
            'app_id'        => config('define.pay.youzan.app_id'),
            'app_type'      => $request->app_type ?:hg_get_agent($_SERVER['HTTP_USER_AGENT']),
            'return_url'    => $request->return_url ?: config('define.pay.plat.return_url'),
            'notify_url'    => config('define.pay.plat.notify_url'),
        ];
        $data = $this->setSignature($pay);
        return $data;
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

    /**
     * @return \Illuminate\Http\JsonResponse
     * 订单状态
     */
    public function orderStatus(){
        $this->validateWithAttribute([
            'content_id'=>'required',
            'content_type'=>'required',
        ],['content_id'=>'内容id','content_type'=>'内容类型']);
        $where = [
            'user_id'=>$this->member['id'],
            'shop_id'=>$this->shop['id'],
            'content_id'=>request('content_id'),
            'content_type'=>request('content_type'),
        ];
        $where['channel'] = env('APP_ENV')=='production'?'production':'pre';
        $order = Order::where($where)->orderBy('order_time','desc')->first();
        $client = $this->initClient(); //初始化 client
        $url = str_replace('{order_no}',$order->center_order_no,config('define.order_center.api.order_detail'));
        try {
            $res = $client->request('GET',$url);
        }catch (\Exception $exception){
            $this->error('error_order');
        }
        $result = $this->errorReturn($res); //出错处理和接收数据
        event(new CurlLogsEvent(json_encode($result),$client,$url));

        return $this->output($result->result['status']); //订单状态
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
            'body'  => $data ? json_encode($data) : '',
        ]);
        return $client;
    }

    /**
     * 同步推广记录 Order $order
    */
    private function syncPromoter(Request $request,$order)
    {
        $contentId = $request->content_id;
        $shopId = $this->shop['id'];
        $contentStatus = PromotionContent::where(['shop_id'=>$shopId,'content_id'=>$contentId,'content_type'=>$order->content_type])->value('id');
        $promoterId = $request->promoter_id;
        //查看该商品是否进行推广
        if($contentStatus){
            $promoterStatus = hg_check_promotion($promoterId,$shopId);
            if($promoterStatus){
                //这边开始同步推广记录
                event(new PromoterRecordEvent($promoterId,$order));
            }
        }
    }

    /**
     * 推广记录
     * @param $order
     * @param $content
     */
    private function promotion($order, $content)
    {
        if ($content->price > 0) {
            $shop_id = $this->shop['id'];
            $promotion_shop = PromotionShop::where(['shop_id'=>$shop_id])->first();
            if(!$promotion_shop){
                return;
            }
            $promoter_content = PromotionContent::select('promotion_rate.promoter_rate', 'promotion_rate.invite_rate')
                ->leftJoin('promotion_rate', 'promotion_rate.id', 'promotion_content.promotion_rate_id')
                ->where(['promotion_content.shop_id' => $shop_id, 'promotion_content.content_id' => $content->hashid,
                    'promotion_content.content_type' => $content->type, 'promotion_content.is_participate' => 1])
                ->first();
            if($promoter_content){
                $member_id = $this->member['id'];
                $shop = Shop::where(['hashid' => $shop_id])->first();
                if ($shop && $shop->is_promotion) {
                    $promoter = MemberBindPromoter::where(['shop_id' => $shop_id, 'member_id' => $member_id, 'state' => 1, 'is_del' => 0])
                        ->whereRaw('(invalid_timestamp > ' . time() . ' or invalid_timestamp=0)')->first();
                    if ($promoter) {
                        $promoter_record = hg_check_promotion($promoter->promoter_id, $shop_id);
                        if ($promoter_record) {
                            $record = PromotionRecord::where(['shop_id'=>$shop_id, 'order_id' => $order->order_id, 'state' => 0])->first();
                            $invite_id = $promotion_shop->is_visit ? $promoter_record->visit_id : null;
                            if ($invite_id) {
                                $invite_record = hg_check_promotion($invite_id, $shop_id);
                                if (!$invite_record) {
                                    $invite_id = '';
                                }
                            }
                            $promoter_commission = $order->price * $promoter_content->promoter_rate / 100;
                            $invite_commission = $order->price * $promoter_content->invite_rate / 100;
                            if ($promoter_content->promoter_rate && $promoter_commission && !$record) {
                                $data = [
                                    'order_id' => $order->order_id,
                                    'shop_id' => $shop_id,
                                    'promotion_id' => $promoter_record->promotion_id,
                                    'visit_id' => $invite_id ?: null,
                                    'buy_id' => $order->user_id,
                                    'content_id' => $order->content_id,
                                    'content_type' => $order->content_type,
                                    'content_title' => $order->content_title,
                                    'deal_money' => $order->price,
                                    'money_percent' => $promoter_content->promoter_rate,
                                    'visit_percent' => $promoter_content->invite_rate,
                                    'state' => 0,
                                    'promoter_commission' => $promoter_commission,
                                    'invite_commission' => $invite_commission,
                                    'create_time' => time()
                                ];
                                PromotionRecord::insert($data);
                            }

                            if ($promotion_shop->is_visit && $promoter_content->invite_rate && $invite_commission && $invite_id) {
                                $record = PromotionRecord::where(['order_id' => $order->order_id, 'state' => 0, 'promotion_type' => 'visit'])->first();
                                if (!$record) {
                                    $visit_data = [
                                        'order_id' => $order->order_id,
                                        'shop_id' => $shop_id,
                                        'promotion_id' => $invite_id,
                                        'visit_id' => null,
                                        'buy_id' => $order->user_id,
                                        'content_id' => $order->content_id,
                                        'content_type' => $order->content_type,
                                        'content_title' => $order->content_title,
                                        'deal_money' => $order->price,
                                        'money_percent' => $promoter_content->invite_rate,
                                        'visit_percent' => 0,
                                        'state' => 0,
                                        'promoter_commission' => $invite_commission,
                                        'invite_commission' => 0,
                                        'create_time' => time(),
                                        'promotion_type' => 'visit'
                                    ];
                                    PromotionRecord::insert($visit_data);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 重新发起支付         
     */
    public function repayment(){
        $this->validateWithAttribute([
            'order_id'      => 'required|alpha_dash|max:64',
            'return_url'    => 'url',
        ],[
            'order_id'      => '订单号',
            'return_url'    => '跳转链接'
        ]);

        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids){
            $order = Order::whereIn('user_id',$member_ids)->where([ 'center_order_no' => request('order_id'), 'shop_id' => $this->shop['id']])->firstOrFail();
        }else {
            $order = Order::where(['user_id' => $this->member['id'], 'center_order_no' => request('order_id'), 'shop_id' => $this->shop['id']])->firstOrFail();
        }
        if($order->pay_status != 0){
            $this->error('pay-status-error');
        }
        switch ($order->source){
            case 'applet':
                $response = $this->repayAppletOrder($order);
                return $this->output($response);
                break;
            default:
                return $this->output(['param' => urlencode(base64_encode(json_encode($this->getResponse(request(), $order))))]);
                break;
        }

    }

    /**
     * 小程序发起支付
     */
    private function repayAppletOrder($order){
        $pay_info = hg_applet_undefined_order($order,$this->shop['id'],Member::where(['uid'=>$order->user_id])->value('openid'));
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

    /**
     * 取消订单
     *
     */
    public function cancelOrder(){

        $this->validateWithAttribute([
            'order_id'  => 'required|alpha_dash|max:64'
        ],[
            'order_id'  => '订单号'
        ]);
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids){
            $order = Order::whereIn('user_id',$member_ids)->where([ 'center_order_no' => request('order_id'), 'shop_id' => $this->shop['id']])->firstOrFail();
        }else {
            $order = Order::where(['user_id' => $this->member['id'], 'center_order_no' => request('order_id'), 'shop_id' => $this->shop['id']])->firstOrFail();
        }
        if($order->pay_status != 0){
            $this->error('pay-status-error');
        }
        //如果是小程序订单需要小关闭小程序订单
        switch ($order->source) {
            case 'applet':
                $this->closeAppletOrder($order);
                break;
        }
        $this->closeOrderCenterOrder();
        $order->pay_status = -1;
        $order->save();
        $promotion_record = PromotionRecord::where(['order_id'=>$order->order_id])->first();
        if($promotion_record){
            //推广员记录关闭
            $promotion_record->state = 2;
            $promotion_record->save();
        }
        return $this->output(['success'=>1]); //正确返回

    }


    /**
     * 关闭小程序订单
     * @param $order
     */
    private function closeAppletOrder($order){
        $options = config('wechat');
        $applet = AppletUpgrade::where('shop_id',$this->shop['id'])->first();
        $applet && $options['app_id'] = $applet->appid;
        $applet && $options['payment']['merchant_id'] = $applet->mchid;
        $applet && $options['payment']['key'] = $applet->api_key;
        $app = new Application($options);
        $payment = $app->payment;
        $result = $payment->close($order->center_order_no);
        event(new CurlLogsEvent($result->toJson(),new Client(['body'  => json_encode(['order_no'=>$order->center_order_no]),]),'https://api.mch.weixin.qq.com/pay/closeorder'));
        if ($result->return_code != 'SUCCESS' || $result->result_code != 'SUCCESS'){
            $this->errorWithText($result->return_code,$result->return_msg);
        }
    }

    /**
     * 关闭订单中心订单
     */
    private function closeOrderCenterOrder(){
        $param = [
            'close_type' => 'cancel',
            'close_reason' => '买家取消',
        ];
        $client = hg_verify_signature($param, '', '', '', $this->shop['id']);
        $url = str_replace('{order_no}', request('order_id'), config('define.order_center.api.order_close'));
        try {
            $return = $client->request('PUT', $url);
            $response = $return->getBody()->getContents();
            event(new CurlLogsEvent($response, $client, $url));
        } catch
        (\Exception $exception) {
            event(new ErrorHandle($exception, 'order_center'));
            $this->error('error_order');
        }
        $response = json_decode($response);
        if ($response->error_code) {
            $this->errorWithText($response->error_code, $response->error_message);
        }
    }

    /**
     * 订单详情
     * @param $order_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderDetail($order_id){
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids){
            $order = Order::whereIn('user_id',$member_ids)
                ->where([
                    'center_order_no'=>$order_id,
                    'shop_id'=>$this->shop['id']
                ])->firstOrFail(['id','content_id','content_title','content_type','content_indexpic','order_time','pay_time','price','pay_status','number','center_order_no as order_id','order_type','order_id as order_no', 'content_price', 'extra_data']);

        }else {
            $order = Order::where([
                'center_order_no'=>$order_id,
                'shop_id'   =>$this->shop['id'],
                'user_id'   => $this->member['id']
            ])->firstOrFail(['id','content_id','content_title','content_type','content_indexpic','order_time','pay_time','price','pay_status','number','center_order_no as order_id','order_type','order_id as order_no', 'content_price', 'extra_data']);
        }

        switch ($order->content_type){
            case 'community':
                if(is_null($order->content_price)) {
                $order->content_price = $order->community ? $order->community->price : 0;
                }
                break;
            case 'course':
                if(is_null($order->content_price)) {
                    $order->content_price = $order->course ? $order->course->price : 0;
                }
                $order->course_type = $order->course ? $order->course->course_type : 'article';
                break;
            case 'member_card':
                if(is_null($order->content_price)) {
                        $extraData = $order->getExtraData();
                        if($extraData) {
                            $order->content_price = $extraData['membercard_option']['price'];
                        } else {
                                $membercard = $order->memberCard;
                                $order->content_price = $membercard ? $membercard->price : 0;
                        }
                }
                break;
            default:
                if(is_null($order->content_price)) {
                    $order->content_price = $order->belongsToContent ? $order->belongsToContent->price : 0;
                }
                break;
        }
        switch ($order->order_type){
            //拼团
            case 3:
                $fight_group_member = FightGroupMember::where(['order_no'=>$order->order_no])->first();
                if($fight_group_member){
                    $fight_group = FightGroup::find($fight_group_member->fight_group_id);
                    $fight_group_activity = FightGroupActivity::find($fight_group->fight_group_activity_id);
                    $order->marketing_status = $fight_group ? $fight_group->status : 'failed';
                    $fight_group && $fight_group->create_time = strtotime('+8 hour',strtotime($fight_group->create_time));
                    $fight_group_activity && $fight_group_activity->end_time = strtotime('+8 hour',strtotime($fight_group_activity->end_time));
                    $order->marketing_create_time = $fight_group ? hg_format_date($fight_group->create_time) : '';
                    $order->marketing_activity_end_time = $fight_group_activity ? hg_format_date($fight_group_activity->end_time) : '';
                    $order->fight_group_id = $fight_group_member->fight_group_id ? : '';
                    $order->fight_group_activity_id = $fight_group->fight_group_activity_id ? : '';
                }else{
                    $fight_group_activity_id = OrderMarketingActivity::where(['order_no'=>$order->order_id,'marketing_activity_type'=>'fight_group'])->value('marketing_activity_id');
                    $order->fight_group_activity_id = $fight_group_activity_id ? : '';
                    $order->marketing_status = 'failed';

                }
                break;
            //团购
            case 4:

                break;
            default:
                break;

        }
        $order->content_indexpic = $order->content_indexpic ? hg_unserialize_image_link($order->content_indexpic) : [];
        $order->order_time = $order->order_time ? hg_format_date($order->order_time) : '';
        $order->pay_time = $order->pay_time ? hg_format_date($order->pay_time) : '';
        $order->makeHidden(['extra_data']);
        return $this->output($order);
    }



}