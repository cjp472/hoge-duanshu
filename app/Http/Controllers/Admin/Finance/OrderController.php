<?php
/**
 * 财务管理-订单管理
 */
namespace App\Http\Controllers\Admin\Finance;

use App\Events\CreateCardRecord;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\JoinCommunityEvent;
use App\Events\NoticeEvent;
use App\Events\OrderStatusEvent;
use App\Events\PayEvent;
use App\Events\PintuanGroupEvent;
use App\Events\SalesTotalEvent;
use App\Events\SubscribeEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Jobs\CheckOrderStatus;
use App\Jobs\Settlement;
use App\Models\Code;
use App\Models\Column;
use App\Models\FightGroupActivity;
use App\Models\FightGroupMember;
use App\Models\InviteCode;
use App\Models\Manage\Customer;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderMarketingActivity;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopClose;
use App\Models\ShopDisable;
use App\Models\ShopFlow;
use App\Models\ShopFunds;
use App\Models\ShopScore;
use App\Models\UserButtonClicks;
use App\Models\UserShop;
use App\Models\VersionExpire;
use App\Models\VersionOrder;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;
use App\Events\SystemEvent;
use App\Models\PromotionRecord;
use App\Models\MessageRecord;
use App\Models\Protocol;
use App\Models\ShopProtocol;

class OrderController extends BaseController
{
    const MESSAGE_TYPE = 'sms';
    const FORM_NAME = '短信包规格';

    public function lists(Request $request)
    {
        $where = [
            'shop_id'   => $this->shop['id'],
            'pay_status'    => 1,
        ];
        $count = $request->count ?: 10;
        $ret = $this->getOrderList($where,$count);
        $total = $this->getTotalPrice($where);
        $today = $this->getTodayTotalPrice($where);
        $count = $this->getTotalPerson($where);
        $ret['statics'] = [
            'total' => $total ?: 0,
            'person'    => $count ?: 0,
            'today' => $today ?: 0
        ];
        return $this->output($ret);
    }

    /**
     * 获取订单列表
     * @param array $where
     * @param int $count
     * @return array
     */
    private function getOrderList($where = [],$count = 10)
    {
        $order = Order::where($where)->orderby('order_time','desc')->paginate($count);
        $this->transTime($order->items());
        return $this->listToPage($order);
    }

    /**
     * 获取订单总金额
     * @param array $where
     * @return mixed
     */
    private function getTotalPrice($where = [])
    {
        return Order::where($where)->sum('price');
    }

    /**
     * 获取总人数
     * @param array $where
     * @return mixed
     */
    private function getTotalPerson($where = [])
    {
        return Order::where($where)->count();
    }

    /**
     * 获取今日订单总金额
     * @param array $where
     * @return mixed
     */
    private function getTodayTotalPrice($where = [])
    {
        return Order::where($where)
            ->where('order_time','>',strtotime(date('Ymd')))
            ->sum('price');
    }

    public function download(Request $request)
    {
        $time = $request->time ? strtotime($request->time) : time();
        $start_time = strtotime(date('Y-m-01',$time));
        $end_time = strtotime(date('Y-m-01', $start_time) . ' +1 month -1 day');
        $order = Order::where([
            'order.shop_id'   => $this->shop['id'],
            'pay_status'    => 1,
        ])
            ->where('pay_time','>=',$start_time)
            ->where('pay_time','<=',$end_time)
            ->select('order.*','member.mobile','member.true_name','member.company','member.position','member.address')
            ->leftJoin('member', 'order.user_id', '=', 'member.uid')
            ->limit(1000)->get();
        $data[] = [
            '订单号','用户ID','昵称','手机号','真实姓名','公司',
            '职位','地址','订单类型','订单内容','订单总额(元)','订单时间',
        ];
        if($order){
            foreach ($order as $key=>$value){
                $data[] = $this->downloadFormat($value);
            }
        }
        Excel::create(date('Y-m',$time).'订单数据', function($excel) use($data) {
            $excel->sheet('订单数据', function($sheet) use($data) {
                $sheet->fromArray($data,null,'A2',false,false);
            });
        })->export('xls');
    }

    private function downloadFormat($value = [])
    {
        return [
            'order_id'  => $value->order_id,
            'user_id'   => $value->user_id,
            'nickname'  => $value->nickname,
            'mobile'    => $value->mobile,
            'true_name' => $value->true_name,
            'company'   => $value->company,
            'position'  => $value->position,
            'address'   => $value->address,
            'content_type'  => config('define.content_type.'.$value->content_type),
            'content_title' => $value->content_title,
            'price'     => $value->price,
            'pay_time'  => date('Y-m-d H:i:s',$value->pay_time),
        ];
    }

    /**
     * 订单中心回调
     * @param Request $request
     * @return array
     */
    public function orderCallback(Request $request)
    {
        //更新订单信息
        $order = Order::where('center_order_no',$request->order_no)->first();
        if(!$order){
            $this->error('no-order');
        }
        $order_status = $order->status;
        $order_status_map = [
            'success'   => 1,
            'closed'    => -1,
            'unpaid'    => 0,
            'paying'    => 0,
            'confirming'=> -6
        ];
        $order_status_text = $request->status;
        $ordercenter_order_status = isset($order_status_map[$order_status_text]) ? $order_status_map[$request->status] : -2;
        //当前订单状态等于推送的订单状态
        if($ordercenter_order_status == $order_status){
            return [
                "error_code" => "0",
                "error_message" => "success",
            ];
        }
        //订单已经成功 不需要再处理
        if($order->pay_status == 1) {
            return [
                "error_code" => "0",
                "error_message" => "success",
            ];
        }
        $order_update_status_time = $order->update_status_time;
        $ordercenter_order_update_status_time = $request->sequence_time[$order_status_text];
        if ($ordercenter_order_update_status_time < $order_update_status_time){
            //推送状态更新时间小于当前状态更新时间 不处理
            return [
                "error_code" => "0",
                "error_message" => "success",
            ];
        }

        if($request->status == 'success' ) {
            $order->pay_status = $ordercenter_order_status;
            if($request->transaction_no) {
                $order->pay_id = $request->transaction_no;
            }
            $order->pay_time = $request->success_datetime ?: time();
        }else{
            $order->pay_status = $ordercenter_order_status;
        }
        //更新状态时间
        $order->update_status_time =  $ordercenter_order_update_status_time;
        $order->saveOrFail();
        if($request->status == 'success') {    //订单支付成功才进行下列操作

            switch ($order->order_type){
                //购买赠送
                case 2 :
                    $this->generateInviteCode($order);
                    event(new PayEvent($order));
                    event(new SalesTotalEvent($order));
                    //更新推广记录表订单状况
                    $this->savePromoterRecord($order);
                    break;
                //拼团业务
                case 3 :
//                    if($request->status != 'confirming'){
//                        return [
//                            "error_code" => "0",
//                            "error_message" => "success",
//                        ];
//                    }
                    break;
                //默认普通购买逻辑
                default :
                    if(Payment::where('order_id',$order->order_id)->value('id')){
                        return 'SUCCESS';
                    }
                    $pay = new Payment;
                    $userSetting = $this->buySetting($order);
                    $paySetting = $this->paySettings($order);
                    $pay->setRawAttributes(array_merge($userSetting, $paySetting));
                    $pay->saveOrFail();
                    switch ($order->content_type) {
                        case 'column':
                        case 'course':
//                            Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id . ':' . $order->content_type, $order->order_id);
                            break;
                        case 'member_card':
                            event(new CreateCardRecord($order));
//                            Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id . ':' . $order->content_type, $order->order_id);
                            break;
                        case 'community':
                            $params = [
                                'shop_id' => $order->shop_id,
                                'community_id' => $order->content_id,
                                'member_id' => $order->user_id,
                                'member_name' => $order->nickname,
                                'source' => 'purchase',
                            ];
                            event(new JoinCommunityEvent($params));
//                            Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id . ':' . $order->content_type, $order->order_id);
                            break;
                        default :
//                            Cache::forever('payment:' . $order->shop_id . ':' . $order->user_id . ':' . $order->content_id, $order->order_id);
                            break;
                    }
                    $paySetting['content_type'] == 'column' && $this->saveContentId($paySetting, $userSetting['user_id']);
                    event(new SubscribeEvent($order->content_id, $order->content_type, $order->shop_id, $order->user_id, $pay->payment_type));
                    event(new PayEvent($order));
                    event(new SalesTotalEvent($order));
                    //更新推广记录表订单状况
                    $this->savePromoterRecord($order);
                    break;

            }
        } else if($request->status = 'confirming' && $order->order_type==3){
            //拼团订单，状态置为待确认
            $order->pay_status = -6;
            $order->save();

            $fight_activity_id = OrderMarketingActivity::where(['order_no'=>$order->center_order_no,'marketing_activity_type'=>'fight_group'])->value('marketing_activity_id');
            $fight_activity = FightGroupActivity::find($fight_activity_id);
            $member_group = Cache::get('fight:group:member:'.$order->center_order_no);
            $member_group = json_decode($member_group);
            $member_id = Member::where(['uid'=>$order->user_id])->value('id');
            //检测是否已经加入当前拼团组
            $is_group_member = FightGroupMember::where([
                'fight_group_id'=> $member_group ? $member_group->group_id : '',
                'member_id'     => $member_id,
                'is_del'        => 0,
                'join_success'  => 1,
                'has_paid'        => 0,
            ])->first();
            if(!$is_group_member && $fight_activity) {
                $group = [
                    'member' => $order->user_id,
                    'order_no' => $order->order_id,
                    'fight_group_activity' => $fight_activity->id,
                ];
                if ($member_group && $member_group->group_id) {
//                            $end_time = $fight_activity->end_time ? strtotime($fight_activity->end_time) : 0;
//                            $key = 'pintuan:group:member:num:' . $member_group->group_id;
//                            Cache::increment($key);
//                            //设置缓存过期时间
//                            Redis::expire(config('cache.prefix') . ':' . $key, $end_time - time() > 0 ? ($end_time - time()) + 3600 : 0);
                    $group['fight_group'] = $member_group->group_id;
                }
                //通知python创建拼团组
                event(new PintuanGroupEvent($group,$order));
            }
            //删除缓存的拼团组和会员关联，释放资源
            Cache::forget('fight:group:member:'.$order->center_order_no);
        }
        if($request->blocked == 'true'){
            event(new SystemEvent(request('seller')['uid'],trans('notice.title.blocked'),trans('notice.content.blocked'),0,-1,'系统管理员'));
        }
        return [
            "error_code" => "0",
            "error_message" => "success",
        ];
    }


    /**
     * 订单支付通知
     * @return array
     */
    public function orderPayCallback(){

        request()->merge([
            'order_no'          => request('out_biz_no'),
            'transaction_no'    => request('asset_detail_no'),
            'status'            => intval(request('result'))==1 ? 'success' : 'failed',
            'success_datetime'  => request('pay_time') ? : time(),
            'pay_callback'      => 1,
        ]);
//支付回调不处理逻辑
//        $this->orderCallback(request());

        return 'SUCCESS';
    }

    private function saveContentId($data,$user_id){
        $content_id = Column::where(['column.hashid'=>$data['content_id'],'column.shop_id'=>$data['shop_id']])->leftJoin('content','content.column_id','=','column.id')->where('content.payment_type',1)->pluck('content.hashid')->toArray();
        if($content_id){
            Redis::sadd('subscribe:h5:'.$data['shop_id'].':'.$user_id,$content_id);
        }
    }

    private function buySetting(Order $order)
    {
        return [
            'user_id'           => $order->user_id,
            'nickname'          => $order->nickname,
            'avatar'            => $order->avatar,
            'payment_type'      => 1,
        ];
    }

    private function paySettings(Order $order)
    {
        return [
            'content_id'            => $order->content_id,
            'content_type'          => $order->content_type,
            'content_title'         => $order->content_title,
            'content_indexpic'      => $order->content_indexpic,
            'order_id'              => $order->order_id,
            'order_time'            => $order->pay_time,
            'price'                 => $order->price,
            'shop_id'               => $order->shop_id,
        ];
    }

    /**
     * 生成赠送邀请码
     * @param $order
     * @return bool
     */
    private function generateInviteCode($order)
    {
        $exists = InviteCode::where(['order_id'=>$order->order_id])->first();
        if($exists || $order->pay_status != 1) {
            return true;
        }
        $inviteCode = new InviteCode();
        $inviteCode->setRawAttributes([
            'shop_id'   => $order->shop_id,
            'type'      => 'share',
            'content_id'    => $order->content_id,
            'content_type'  => $order->content_type,
            'content_title' => $order->content_title,
            'content_indexpic' => $order->content_indexpic,
            'order_id'      => $order->order_id,
            'buy_time'      => $order->pay_time,
            'user_id'       => $order->user_id,
            'user_name'     => $order->nickname,
            'avatar'        => $order->avatar,
            'price'         => $order->price,
            'total_num'     => intval($order->number),
        ]);
        $order->content_type == 'member_card' && $inviteCode->setExtraData($order->getExtraData());
        $inviteCode->save();
        if ($inviteCode->getKey()) {
            for($i=1;$i<=intval($order->number);$i++){
                $code[] = [
                    'shop_id' => $order->shop_id,
                    'code_id' => $inviteCode->getKey(),
                    'code'    => rand(1000, 9999) . '-' . rand(1000, 9999) . '-' . rand(1000, 9999),
                    'status'  => 0
                ];
            }
            Code::insert($code);
        }
        return true;

    }

    public function getMemberOrder(Request $request,$user_id)
    {
        $count = $request->count ?: 10;
        $channel = env('APP_ENV') == 'production' ? 'production' : 'pre';
        $order = Order::where([
            'shop_id'   => $this->shop['id'],
            'user_id'   => $user_id,
            'pay_status'    => 1,
            'channel' => $channel
        ])->select('pay_time','content_title','content_type','price','order_type','source', 'center_order_no', 'order_id')
            ->orderby('pay_time','desc')
            ->paginate($count);
        $this->transTime($order->items());
        return $this->output($this->listToPage($order));
    }

    private function transTime($data)
    {
        foreach ($data as $v){
            $v->pay_time && $v->pay_time = date('Y-m-d H:i:s',$v->pay_time);
            $v->order_time && $v->order_time = date('Y-m-d H:i:s',$v->order_time);
        }
    }

    /**
     * 购买版本订单回调
     * @param Request $request
     */
    public function webhooks(Request $request){
        $param = $request->input();
        $order = VersionOrder::whereIn('order_no',array_column($param,'order_no'))->get();

        if($order->isEmpty()){
            $data = $param[0];
            if(!isset($data['status']) || ($data['status'] && $data['status'] == 'success')){
                //订单支付成功
                $info = $this->formatOrderData($data);
                VersionOrder::insert($info);
                if(self::MESSAGE_TYPE == $data['type']){
                    $this->sendSuccessByMessage($data);
                }elseif($data['type']=='certification_services'){    //认证回调
                    $this->processVerify($data);
                }elseif($data['type'] == 'permission'){
                    $data['type'] == 'permission';
                    $shop = $data['user'];
                    if(!$shop){
                        $this->error('no_shop');
                    }
                    if($data['product'] == PRODUCT_STANDARD_IDENTIFY){
                        $version = VERSION_STANDARD;
                        $notice_key = 'version_standard';
                    } else if($data['product'] == PRODUCT_ADVANCED_IDENTIFY){
                        $version = VERSION_ADVANCED;
                        $notice_key = 'version';
                    }
                    if(isset($version) && isset($notice_key)){
                        Shop::where('hashid', $shop)->update(['version' => $version]);
                        Customer::where('shop_id', $shop)->update(['cooperation' => 1]);
                        $userShop = UserShop::where('shop_id', $shop)->get();
                        if ($userShop) {
                            foreach ($userShop as $v) {
                                Cache::forever('change:'.$v->user_id,1);
                                Cache::forget('share:'.$v->shop_id);
                            }
                        }
                        $info['sku'] = unserialize($info['sku']);
                        $this->sendSuccessBuyNotice($shop,$info, $version, $notice_key);
                        $this->setProtocolStatus($data);
                        ShopDisable::shopUpgradeAdvanced($shop);
                    }
                    $this->postWebHooks($shop, $param);

                }elseif($data['type']=='token'){
                    $product_num_str = $data['sku']['properties'][0]['v'];
                    $quantity = intval($data['quantity']);
                    $product_num = intval($product_num_str) * 100 * $quantity;
                    $product_name = '短书币 '.$product_num_str;
                    $total_price = $data['total'] * 100;
                    $unit_price = intval($total_price/$quantity);
                    $param = [
                        'shop_id' => $data['user'],
                        'transaction_no' => trim($data['order_no']),
                        'product_type' => $data['type'],
                        'product_name' => $product_name,
                        'type' => 'income',
                        'unit_price' => $unit_price,
                        'quantity' => $quantity,
                        'total_price' => $total_price,
                        'amount' => $product_num
                    ];
                    ShopFunds::createFunds($param, time());

                }

            }elseif($data['status'] && $data['status'] == 'closed'){
                $shop = $data['user'];
                //订单支付失败
                if(self::MESSAGE_TYPE == $data['type']){
                    $this->sendFailByMessage($shop);
                }elseif($data['type']=='permission'){
                    $shop = $data['user'];
                    $this->sendFailBuyNotice($shop);
                }elseif ($data['type']=='token'){
                    $shop = $data['user'];
                    $this->sendFailScoreNotice($shop);
                }else{
                    $this->sendFailBuyNotice($shop);
                }
            }
            return $this->output(['success'=>1]);
        }
        return $this->error('order_already_handle');
    }

    private function sendFailScoreNotice($shop_id){
        event(new SystemEvent($shop_id,trans('notice.title.score.fail'),trans('notice.content.score.fail'),0,-1,'系统管理员'));
    }

    private function sendScoreBuyNotice($data){
        $content = trans('notice.content.score.recharge');
        $content  = str_replace('{score}',$data['score'],$content);
        event(new SystemEvent($data['shop_id'],trans('notice.title.score.recharge'),$content,0,-1,'系统管理员'));
    }

    private function formatScoreData($data)
    {
        $score = intval($data['sku']['properties'][0]['v']);
        return [
            'shop_id' => $data['user'],
            'order_id' => trim($data['order_no']),
            'order_type' => 'recharge',
            'order_price' => $data['total'],
            'order_time' => strtotime($data['success_time']),
            'score' => intval($data['quantity']) * $score,
            'project' => $data['sku'] ? $data['sku']['properties'][0]['v'] : '',
            'last_score' => $this->getShopScore($data['user']),
        ];
    }

    private function getShopScore($shop_id){
        $client = $this->initClient($shop_id);//生成签名
        $url = config('define.service_store.api.shop_score');
        $res = $client->request('get',$url);
        $data = json_decode($res->getBody()->getContents());
        if($res->getStatusCode() !== 200){
            $score = 0;
        }elseif($res && $data->error_code){
            $score = 0;
        }else{
            $score = $data->result->token;
        }
        return $score;
    }

    private function initClient($data){
        $time = time();
        $sign_array = [
            'access_key' => config('define.service_store.app_id'),
            'access_secret' => config('define.service_store.app_secret'),
            'timestamp'     => $time,
        ];
        $sign_string = urldecode(http_build_query($sign_array));
        $sign = strtoupper(hash('md5',$sign_string));
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-API-KEY' => config('define.service_store.app_id'),
                'x-API-TIMESTAMP' => $time,
                'x-API-SIGNATURE' => $sign,
                'AUTHORIZATION'   => $data,
            ],
        ]);
        return $client;
    }

    private function processVerify($data)
    {
        $version = VersionExpire::where(['hashid' => $data['user'], 'version' => VERSION_BASIC, 'is_expire' => 0])->orderby('expire', 'desc')->first();
        if (!$version) {
            $version = new VersionExpire();
            $version->hashid = $data['user'];
            $version->version = VERSION_BASIC;
            $version->start = time();
            $version->expire = strtotime('+1year', time());
            $version->is_expire = 0;
            $version->method = 1;
            $version->save();
        } else {
            $version->start = time();
            $version->expire = strtotime('+1year', time());
            $version->is_expire = 0;
            $version->save();
        }
        Shop::where(['hashid' => $data['user']])->update(['verify_expire' => strtotime('+1year', time())]);
    }

    private function setProtocolStatus($data)
    {
        $pro = Protocol::select('id','title')->where('type','order')->first();
        if($pro){
            $info = ShopProtocol::where('shop_id',$data['user'])->first();
            $skuPro = $data['sku']['properties'];
            $year = '';
            foreach($skuPro as $value){
                if('有效期' == $value['k']){
                    $year = $value['v'];
                }
            }
            $content = [
                'name' => '',
                'title' => $pro->title,
                'price' => $data['total'],
                'time' => $year
            ];
            if(!$info){
                $result = [
                    'p_id' => $pro->id,
                    'shop_id' => $data['user'],
                    'create_time' => strtotime($data['success_time']),
                    'content' => serialize($content),
                    'status' => 1
                ];
                ShopProtocol::insert($result);
            }else{
                $content = $info->content;
                $isExpire = VersionExpire::where(['hashid'=>$data['user'],'is_expire'=>0])->orderBy('id')->first();
                if($isExpire){
                    //续买
                    $expireTime = VersionExpire::select('expire')->where(['hashid'=>$data['user'],'is_expire'=>0])->orderBy('id','desc')->first();
                    $content['price'] = $content['price']+$data['total'];
                    $content['time'] = ceil(($expireTime->expire - $isExpire->start)/31536000).'年';
                    $info->create_time = $isExpire->start;
                    $info->status = 1;
                    $info->content = serialize($content);
                    $info->save();
                }else{
                    //过期购买
                    $content['price'] = $data['total'];
                    $content['time'] = $year;
                    $info->create_time = strtotime($data['success_time']);
                    $info->status = 1;
                    $info->content = serialize($content);
                    $info->save();
                }
            }
        }
    }

    private function sendSuccessByMessage($data)
    {
        if(!$data['sku']){
            $this->error('required',['attribute'=>'sku字段']);
        }
        $shop = Shop::where('hashid',$data['user'])->first();
        if(!$shop){
            $this->error('no-shop');
        }
        $skuPro = $data['sku']['properties'];
        $number = 0;
        foreach($skuPro as $value){
            if(self::FORM_NAME == $value['k']){
                $number = intval(preg_replace('/\D/s', '', $value['v']));
            }
        }
        $count = intval($data['quantity']);
        $total = $count ? $count*$number : $number;
        try{
            MessageRecord::insert([
                'shop_id' => $data['user'],
                'user' => '短书平台',
                'type' => 2,
                'number' => $total,
                'create_time' => time(),
                'order_no' => $data['order_no']
            ]);
            $shop->increment('message',$total);
        }catch(\Exception $e){
            $this->error($e->getMessage());
        }
        event(new SystemEvent($data['user'],'短信充值成功','恭喜你成功充值'.$total.'条短信!',0,-1,'系统管理员'));
    }

    private function sendFailByMessage($shop_id)
    {
        event(new SystemEvent($shop_id,'充值失败','未能成功充值,请重新尝试!',0,-1,'系统管理员'));
    }

    private function sendSuccessBuyNotice($shop_id, $info, $version, $notice_key)
    {
        $content = trans('notice.content.success.'.$notice_key);
        $expire = VersionExpire::where('hashid',$shop_id)
            ->where('version',$version)->orderBy('expire','desc')->first();
        if($expire){
            $start = $expire->expire > time() ? $expire->expire : time();
        }else{
            $start = $info['success_time'] ?: time();
        }
        $month = config('define.year_time.' . $info['sku']['properties'][0]['v']);
        $str = '+'.$month.' month';
        $end = strtotime($str,$start);
        if($month  == 12){ $year = "1年";}
        elseif($month  == 24){ $year = '2年';}
        elseif($month  == 6){ $year = '半年';}
        else{$year = $info['sku']['properties'][0]['v'];}

        $content  = str_replace([
            '{start_date}','{end_date}','{month}'
        ],[
            date('Y.m.d H:i',$start),
            date('Y.m.d H:i',$end),
            $year
        ],$content);

        event(new SystemEvent($shop_id,trans('notice.title.success.'.$notice_key),$content,0,-1,'系统管理员'));

        $ve = new VersionExpire();
        $ve->hashid = $shop_id;
        $ve->version = $version;
        $ve->start = $start;
        $ve->expire = $end;
        $ve->method = 1;
        $ve->save();
//        Shop::where('hashid',$shop_id)->update(['verify_expire'=>$end]);
        //删除改店铺点击高级版升级按钮存储的浏览记录，
        if($version == VERSION_ADVANCED) {
            UserButtonClicks::where('shop_id', $shop_id)->where('type', VERSION_ADVANCED)->delete();
        }
    }

    private function sendFailBuyNotice($shop_id )
    {
        event(new SystemEvent($shop_id,trans('notice.title.fail.version'),trans('notice.content.fail.version'),0,-1,'系统管理员'));
    }

    private function formatOrderData($data){
        return [
            'shop_id'      => $data['user'],
            'product_id'   => trim($data['product']),
            'product_name' => trim($data['name']),
            'brief'        => trim($data['brief']),
            'category'     => trim($data['category']),
            'thumb'        => trim($data['thumb']),
            'type'         => trim($data['type']),
            'sku'          => $data['sku'] ? serialize($data['sku']) : '',
            'unit_price'   => $data['unit_price'],
            'quantity'     => intval($data['quantity']),
            'total'        => $data['total'],
            'meta'         => $data['meta'] ? serialize($data['meta']) : '',
            'order_no'     => trim($data['order_no']),
            'success_time' => strtotime($data['success_time']),
            'create_time'  => time(),
        ];
    }

//    private function savePromoterRecord($order)
//    {
//        $promoterRecord = PromotionRecord::where(['order_id'=>$order->order_id,'state'=>0])->first();
//        if($promoterRecord){
//            $promoterRecord->state = 1;
//            $promoterRecord->finish_time = time();
//            $promoterRecord->save();
//        }
//    }

    //存储空间不足通知
    private function sendStorageNotice($shop_id){
        $content = trans('notice.content.score.storage_arrears');
        event(new SystemEvent($shop_id,trans('notice.title.score.storage_arrears'),$content,0,-1,'系统管理员'));
    }

    //流量余额不足通知
    private function sendFlowNotice($shop_id){
        $content = trans('notice.content.score.flow_arrears');
        event(new SystemEvent($shop_id,trans('notice.title.score.flow_arrears'),$content,0,-1,'系统管理员'));
    }

    /**
     * @param Request $request
     * 结算每天消费的存储空间和流量（服务商城流量推送回调接口）
     */
//    public function settlement(Request $request)
//    {
//        $param = $request->input();
//        if( $param['total_consume'] == 0 ) {
//            return $this->output(['success' => 1]);
//        }
//        $data = [];
//        $now_time = date('Y-m-01 00:00:00', time());
//        $time = date_add(date_create($now_time), date_interval_create_from_date_string('1 months'));
//        $start_time = strtotime(date('Y-m-01 00:00:00', time()));
//        $end_time = strtotime(date_format($time, 'Y-m-d H:i:s'));
//        $shop = Shop::where(['hashid' => $param['app_id']])->select('version','flow','storage')->first();
//        if ($shop) { //店铺存在
//            $default_storage = $shop->storage;
//            $default_flow = $shop->flow;
//            //处理存储数据
//            if (is_array($param) && $param['cloud_type'] == 'qcloud_cos' && intval($param['total_consume']) != 0) {
//                $data = [
//                    'shop_id' => $param['app_id'],
//                    'numberical' => $param['total_consume'] / 1024,      //单位kb
//                    'remark' => 'storage',
//                    'time' => $param['charge_time'],
//                    'unit_price' => 0,
//                    'price' => 0,
//                    'flow_type' => 0,
//                    'qcloud_type' => $param['qcloud_type'],
//                    'source' => getenv('APP_ENV'),
//                ];
//                $storage = ShopFlow::where(['remark' => 'storage', 'shop_id' => $param['app_id'], 'flow_type' => 0])->whereBetween('time', [$start_time, $end_time])->sum('numberical');
//                if ($default_storage - $storage <= 0) {
//                    $data['unit_price'] = DEFAULT_STORAGE_UNIT_PRICE;
//                    $data['price'] = DEFAULT_STORAGE_UNIT_PRICE * ($param['total_consume'] / (1048576 * 1024));
//                    $data['flow_type'] = 1;
//                } elseif ($default_storage && ((($default_storage - $storage) / $default_storage) < 0.1)) {
//                    event(new SystemEvent($param['app_id'], trans('notice.title.score.not_enough_storage'), trans('notice.content.score.not_enough_storage'), 0, -1, '系统管理员'));
//                }
//                //处理流量数据
//            } elseif (is_array($param) && $param['cloud_type'] == 'qcloud_cdn' && intval($param['total_consume']) != 0) {
//                $data = [
//                    'shop_id' => $param['app_id'],
//                    'numberical' => $param['total_consume'] / 1024,      //单位kb
//                    'remark' => 'flow',
//                    'time' => $param['charge_time'],
//                    'unit_price' => 0,
//                    'price' => 0,
//                    'flow_type' => 0,
//                    'qcloud_type' => $param['qcloud_type'],
//                    'source' => getenv('APP_ENV'),
//                ];
//                $flow = ShopFlow::where(['remark' => 'flow', 'shop_id' => $param['app_id'], 'flow_type' => 0])->whereBetween('time', [$start_time, $end_time])->sum('numberical');
//                if ($default_flow - $flow <= 0) {
//                    $data['unit_price'] = DEFAULT_FLOW_UNIT_PRICE;
//                    $data['price'] = DEFAULT_FLOW_UNIT_PRICE * ($param['total_consume'] / (1048576 * 1024));
//                    $data['flow_type'] = 1;
//                } elseif ($default_flow && ((($default_flow - $flow) / $default_flow) < 0.1)) {
//                    event(new SystemEvent($param['app_id'], trans('notice.title.score.not_enough_flow'), trans('notice.content.score.not_enough_flow'), 0, -1, '系统管理员'));
//                }
//            }
//            $sf = new ShopFlow($data);
//            $sf->save();
//        }
//        return $this->output(['success' => 1]);
//    }

    /**
     * 接收服务商城的存储流量统计推送
     * @param Request $request
     */
    public function settlement(Request $request) {
        $param = $request->input();
        $shop = Shop::where(['hashid' => $param['app_id']])->select('version','flow','storage')->first();
        if ($shop) {
            $data = [
                'shop_id' => $param['app_id'],                       // 店铺id
                'numberical' => $param['total_consume'] / 1024,      // 单位kb
                'time' => $param['charge_time'],                     // 统计结算时间
                'unit_price' => 0,                                   // 单价
                'price' => 0,                                        // 消费价格
                'flow_type' => 0,                                    // 类型（1: 消费, 0: 免费）
                'qcloud_type' => $param['qcloud_type'],              // 文件类型
                'cloud_type' => $param['cloud_type'],                // 统计类型 (qcloud_cos: 存储, qcloud_cdn: 流量)
                'source' => getenv('APP_ENV'),              // 系统环境 （pre: 预发布, production: 线上）
            ];
            $sf = new ShopFlow($data);
            $sf->save();
        }
    }

    private function savePromoterRecord($order)
    {
        $promoterRecord = PromotionRecord::where(['order_id'=>$order->order_id,'state'=>0,'promotion_type'=>'promotion'])->first();
        if ($promoterRecord) {
            $promoterRecord->state = 1;
            $promoterRecord->finish_time = time();
            $promoterRecord->save();
            //更新邀请推广记录状态
            $visit_record = PromotionRecord::where(['order_id'=>$order->order_id,'state'=>0,'promotion_type'=>'visit'])->first();
            if($visit_record){
                $visit_record->state = 1;
                $visit_record->finish_time = time();
                $visit_record->save();
            }

        }
    }



    /**
     * 订单查询汇总数据
     */
    public function orderSummarySearch(){


        $this->validateWithAttribute(['id'=>'required|alpha_dash']);

        $param = [
            //今日
            [
                'type' => [
                    'fights_group'
                ],
                'status' => [
                    'success'
                ],
                'extra_data' => [
                    'fight_group_activity_id' => request('id'),
                ],
                'start_time' => strtotime(date('Y-m-d 00:00:00')),
                'end_time' => strtotime(date('Y-m-d 23:59:59')),
            ],
            //昨日
            [
                'type' => [
                    'fights_group'
                ],
                'status' => [
                    'success'
                ],
                'extra_data' => [
                    'fight_group_activity_id' => request('id'),
                ],
                'start_time' => strtotime(date('Y-m-d 00:00:00',strtotime('-1 day'))),
                'end_time' => strtotime(date('Y-m-d 23:59:59',strtotime('-1 day'))),
            ],
            //七日
            [
                'type' => [
                    'fights_group'
                ],
                'status' => [
                    'success'
                ],
                'extra_data' => [
                    'fight_group_activity_id' => request('id'),
                ],
                'start_time' => strtotime(date('Y-m-d 00:00:00',strtotime('-7 day'))),
                'end_time' => strtotime(date('Y-m-d 23:59:59')),
            ],
            //总的
            [
                'type' => [
                    'fights_group'
                ],
                'status' => [
                    'success'
                ],
                'extra_data' => [
                    'fight_group_activity_id' => request('id'),
                ],
                'start_time' => 1,
                'end_time' => time(),
            ],
        ];

        $appId = config('define.order_center.app_id');
        $appSecret = config('define.order_center.app_secret');
        $timesTamp = time();
        $client = hg_verify_signature($param,$timesTamp,$appId,$appSecret,$this->shop['id']);
        try{
            $res = $client->request('POST',config('define.order_center.api.m_order_summary_search'));
            $return = $res->getBody()->getContents();
            event(new CurlLogsEvent($return,$client,config('define.order_center.api.m_order_summary_search')));
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
            $this->error('error_order');
        }

        $response = json_decode($return,1);
        if($response && $response['error_code']){
            $this->errorWithText($response['error_code'],$response['error_message']);
        }
        $data = [];
        if(isset($response['result'])){
            $order_key = ['today_order','yesterday_order','sevenday_order','all_order'];
            foreach ($response['result'] as $key=>$item) {
                isset($order_key[$key]) && $data[$order_key[$key]] = $item;
            }
        }

        return $this->output($data);


    }


    /**
     * 订单状态修改
     */
    public function checkOrderStatus(){


        if(\request('pay_status') == -6) {
            $res = [];
            $order = Order::whereIn('pay_status', [-6])
                ->where('center_order_no', '!=', '')
                ->where('order_type', 3)
                ->where('source', 'applet')
//            ->where('channel', env('APP_ENV'))
                ->get(['id', 'center_order_no', 'shop_id', 'pay_status', 'channel']);
            foreach ($order as $item) {
                $client = hg_verify_signature();
                $url = str_replace('{order_no}', $item->center_order_no, config('define.order_center.api.order_detail'));
                if (getenv('APP_ENV') == 'pre' && $item && $item->channel == 'production') {
                    $url = str_replace('storetest', 'store', $url);
                    $client = hg_verify_signature([], '', env('ORDER_CENTER_PRODUCTION_APPID'), env('ORDER_CENTER_PRODUCTION_APPSECRET'));
                } else if(getenv('APP_ENV') == 'production' && $item->channel == 'pre'){
                    $url = str_replace('store', 'storetest', $url);
                    $client = hg_verify_signature([],'',env('ORDER_CENTER_PRE_APPID'),env('ORDER_CENTER_PRE_APPSECRET'));
                }
                try {
                    $return = $client->request('GET', $url);
                } catch (\Exception $exception) {
                    continue;
                }
                $response = json_decode($return->getBody()->getContents());
                if ($response && !$response->error_code && $response->result) {
                    $result = $response->result;
                    $status = [
                        'success' => 1,
                        'closed' => -1,
                        'unpaid' => 0,
                        'confirming' => -6
                    ];
                    $order_pay_status = isset($status[$result->status]) ? $status[$result->status] : $item->pay_status;
                    if ($order_pay_status != $item->pay_status) {
                        $item->pay_status = $order_pay_status;
                        $item->save();
                    }
                    file_put_contents(storage_path('logs/order.txt'), var_export($item, 1), FILE_APPEND);
                }
            }
        }else {

            $count = request('count') ?: 100;
            $total = Order::whereIn('pay_status', [-1, -2])
                ->where('center_order_no', '!=', '')
                ->where('order_type', 1)
//            ->where('channel', env('APP_ENV'))
                ->count();
            for ($i = 1; $i <= ceil($total / $count); $i++) {
                request()->merge(['page' => $i]);
                dispatch((new CheckOrderStatus(request()->input()))->onQueue(QUEUE_NAME));
            }
        }
        return $this->output(['success'=>1]);

    }

    private function postWebHooks($shop_id, $param)
    {
        try {
            $timestamp = time();
            $app_id = config('define.inner_config.sign.key');
            $app_secret = config('define.inner_config.sign.secret');
            $client = hg_verify_signature($param, $timestamp, $app_id, $app_secret, $shop_id);
            $url = config('define.python_duanshu.api.internal_order');
            $res = $client->request('POST', $url);
            $response = $res->getBody()->getContents();
            event(new CurlLogsEvent($response, $client, $url));
        } catch (\Exception $exception) {
            event(new ErrorHandle($exception, 'notify python internal order'));
        }
    }
}