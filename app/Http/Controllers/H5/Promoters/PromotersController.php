<?php

namespace App\Http\Controllers\H5\Promoters;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\SystemEvent;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\MemberBindPromoter;
use App\Models\Shop;
use App\Models\Cash;
use App\Models\Member;
use App\Models\Promotion;
use App\Models\SystemNotice;
use Illuminate\Http\Request;
use App\Models\PromotionShop;
use App\Models\PromotionRecord;
use App\Models\PromotionContent;
use App\Models\SystemNoticeUser;
use App\Models\MemberCard;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\H5\BaseController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;


class PromotersController extends BaseController
{
    const PAGINATE = 20;
    const CONTENT_COLUMN = 'column';
    const CONTENT_COURSE = 'course';
    const CONTENT_MEMBER_CARD = 'member_card';

    const ORDER_BY_TYPE = ['create_time', 'commission', '-create_time', '-commission', 'price', '-price'];

    /**
     * 基本信息接口
    */
    public function getBasicInfo(Request $request)
    {
        $applet_audit_status = $this->checkAppletAuditStatus();
        if($applet_audit_status) {
            return $this->output();
        }
        $userId = $this->member['id'];
        $shopId = $this->shop['id'];
        $shop = Shop::select('title')->where('hashid',$shopId)->first();
        $promotionShop = PromotionShop::where('shop_id',$shopId)->first();

//        $mobile = Member::where('uid',$this->member['id'])->value('mobile');
//        $member_ids = Redis::smembers('mobileBind:'.$this->shop['id'].':'.$mobile);
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids) {
            $userStatus = Promotion::select('is_delete','state','delete_time')->where(['shop_id'=>$shopId])->whereIn('promotion_id',$member_ids)->orderBy('is_delete','asc')->orderByDesc('apply_time')->first();
        }else{
            $userStatus = Promotion::select('is_delete','state','delete_time')->where(['promotion_id'=>$userId,'shop_id'=>$shopId])->orderByDesc('apply_time')->first();
        }
        $userInfo = Member::select('nick_name','avatar','mobile')->where('uid',$userId)->first();
        if($userStatus){
            $userStatus->state = intval($userStatus->state);
            $userStatus->is_delete = intval($userStatus->is_delete);
        }
        $data = [
            'shop' => [
                'promoter' => $promotionShop ? : [],
                'title' => $shop->title
            ],
            'user' => [
                'promoter' => $userStatus ?  : [],
                'nick_name' => $userInfo ? $userInfo->nick_name : '',
                'avatar' => $userInfo ? $userInfo->avatar : '',
                'mobile' => $userInfo ? $userInfo->mobile : '',
                'is_first'  => Cache::get('promoter:check:state:'.$userId) ? 1 : 0,
            ]
        ];
        return $this->output($data);
    }

    /**
     * 申请推广员验证手机号
    */
    public function checkMobile()
    {
        $this->validateWithAttribute([
            'mobile' => 'required|regex:/^1[3,4,5,7,8]\d{9}$/'
        ], [
            'mobile' => '手机号',
        ]);
        $this->check(request('mobile'));
        return $this->output(['success'=>1]);
    }

    /**
     * 申请推广员
    */
    public function applyPromoter()
    {
        $this->validateWithAttribute([
            'mobile' => 'max:11',
            'code' => 'numeric',
        ], [
            'mobile' => '手机号',
            'code' => '验证码',
        ]);

        $member_id = $this->member['id'];
        $shop_id = $this->shop['id'];
        $mobile = request('mobile');
        $code = request('code');
        $invite_id = request('visit_id');


        $promotion_shop = PromotionShop::where(['shop_id' => $shop_id])->firstOrFail();
        $member = Member::where('uid', $this->member['id'])->firstOrFail();
        if (($promotion_shop->open_bind_mobile || $member->source != 'wechat') && !$mobile) {
            $this->error('mobile-empty');
        }

        if ($mobile) {
            if ($code != Cache::get('mobile:code:' . request('mobile'))) {
                $this->error('mobile_code_error');
            }
            //检测手机号是否已经存在
            $this->check($mobile);
        }

        $version = Shop::where('hashid', $shop_id)->value('version');
        if(VERSION_BASIC == $version){
                $this->error('shop_version_too_low');
        }

        $is_check = $promotion_shop->is_check;
        //如果没有手机号，将手机号绑定到当前会员
        if (!$member->mobile && $mobile) {
            $member->mobile = $mobile;
            $member->save();
            Redis::sadd('mobileBind:' . $this->shop['id'] . ':' . request('mobile'), $this->member['id']); //做h5和小程序的兼容
            Redis::sadd('mobileBind:' . $this->shop['id'] . ':' . $member->source . ':' . request('mobile'), $this->member['id']);  //做判断
        }

        $member_ids = hg_is_same_member($member_id, $shop_id);

        //判断是否有邀请人id
        if ($promotion_shop->is_visit && $invite_id && !in_array($invite_id, $member_ids)) {
            //小程序、h5互通数据判断
            $userStatus = hg_check_promotion($invite_id, $this->shop['id']);
            !$userStatus && $this->error('member_is_not_promoter');
            $data['visit_id'] = $invite_id;
        }

        //先查询当前用户是否申请推广员，如果没有再查询已绑定的手机号的账户
        $result = Promotion::where(['promotion_id' => $member_id])->where(['shop_id' => $this->shop['id']])->first();
        if (!$result && $member_ids && $member_ids > 1) {
            $result = Promotion::whereIn('promotion_id', $member_ids)
                ->where(['shop_id' => $shop_id])
                ->orderBy('is_delete', 'asc')
                ->orderBy('apply_time', 'asc')
                ->first();
        }
        //不存在记录则新增 存在则更新
        if (!$result) {
            $data['shop_id'] = $shop_id;
            $data['promotion_id'] = $member_id;
            $data['apply_time'] = time();
            $data['is_delete'] = 0;
            $data['delete_time'] = 0;
            $data['state'] = $is_check ? 2 : 1;
            Promotion::insert($data);
            $userInfo = Member::where('uid', $member_id)->first();
            if ($userInfo && !$userInfo->mobile && $mobile) {
                $userInfo->mobile = $mobile;
                $userInfo->save();
            }
        } else {
            if ($promotion_shop->is_visit && $invite_id && !in_array($invite_id, $member_ids)) {
                $result->visit_id = $invite_id;
            }
            $result->is_delete = 0;
            $result->state = $is_check ? 2 : 1;
            $result->apply_time = time();
            $result->save();
        }
        event(new SystemEvent($this->shop['id'], '您有新的推广员申请', '您有新的推广员申请', 0, -1, '系统管理员'));
        return $this->output(['success' => 1]);
    }

    private function check($mobile)
    {
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        $result = Member::whereNotIn('uid',$member_ids)
            ->where(['shop_id'=>$this->shop['id'],'source'=>$this->member['source'],'mobile'=>$mobile])
            ->value('uid');
        if($result){
            $this->error('mobile_used');
        }
        //不验证当前会员是否绑定手机号，如果没绑定，直接将该手机号帮到当前账户
//        $bindMobile = Member::where(['uid'=>$userId])->value('mobile');
//        if($bindMobile && $bindMobile != request('mobile')){
//            $this->error('is_not_bind_mobile');
//        }
    }

    /**
     * 新增系统通知
    */
    private function sendSystemNotice()
    {
        $shopId = $this->shop['id'];
        $data = [
            'shop_id' => $shopId,
            'title' => '您有新的推广员申请',
            'user_id' => -1,
            'user_name' => '系统管理员',
            'send_type' => 0,
            'send_time' => time(),
            'is_del' => 0,
        ];
        $noticeId = SystemNotice::insertGetId($data);
        SystemNoticeUser::insert([
            'shop_id' => $shopId,
            'notice_id' => $noticeId,
            'is_read' => 0
        ]);
    }

    /**
     * 推广员列表
    */
    public function getPromotersList()
    {
        $userId = $this->member['id'];
        $shopId = $this->shop['id'];
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        //指定店铺下当前用户的线下推广员(没有被清退的)
        $sql = Promotion::select('member.avatar','member.nick_name','promotion.visit_id','member.uid')
            ->where(['promotion.shop_id'=>$shopId,'promotion.state'=>1,'promotion.is_delete'=>0]);
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
//        $member_ids = [];
        if($member_ids){
            $sql->whereIn('promotion.visit_id',$member_ids);
        }else{
            $sql->where('promotion.visit_id',$userId);
        }
        $persons = $sql->leftJoin('member','promotion.promotion_id','=','member.uid')
            ->paginate($count);
        if(!$persons->isEmpty()){
            foreach($persons as $key => $value){
                //当前用户线下推广员成交的订单和当前用户所获得的佣金
                $orderNum = PromotionRecord::where(['promotion_id'=>$value->uid,'shop_id'=>$shopId,'state'=>1,'promotion_type'=>'promotion'])->count();
                $underTotalOrder = PromotionRecord::where(['promotion_id'=>$value->uid,'shop_id'=>$shopId,'state'=>1,'promotion_type'=>'promotion'])->get()->toArray();
                $totalMoney = 0;
                if($underTotalOrder){
                    foreach($underTotalOrder as $orders){
                        $totalMoney +=($orders['visit_percent']/100)*$orders['deal_money'];
                    }
                }
                $value->setAttribute('order_num',$orderNum);
                $value->setAttribute('total_money',$totalMoney);
            }
        }
        return $this->listToPage($persons);
    }

    /**
     * 邀请推广员列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function inviteList()
    {
        $member_id = $this->member['id'];
        $shop_id = $this->shop['id'];
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $page = request('page') ? intval(request('page')) : 1;
        $offset = ($page - 1) * $count;
        $promotion_record = PromotionRecord::where(['shop_id' => $shop_id, 'state' => 1])
            ->selectRaw('promotion_id, count(id) as order_count, sum(invite_commission) as commission_total')
            ->groupBy('promotion_id');
        $query_set = Promotion::select('member.avatar', 'member.nick_name', 'sub.order_count', 'sub.commission_total')
            ->where(['promotion.shop_id' => $shop_id, 'promotion.state' => 1]);
        $db = app('db');
        $member_ids = hg_is_same_member($member_id, $shop_id);
        if ($member_ids && count($member_ids) > 1) {
            $query_set = $query_set->whereIn('promotion.visit_id', $member_ids);
            $promotion_record = $promotion_record->whereIn('promotion_record.visit_id', $member_ids);
        } else {
            $query_set = $query_set->where('promotion.visit_id', $member_id);
            $promotion_record = $promotion_record->where('promotion_record.visit_id', $member_id);
        }
        $total = $query_set->count();
        $query_set = $query_set->leftJoin('member', 'promotion.promotion_id', '=', 'member.uid')
            ->join($db->raw("({$promotion_record->toSql()}) as hg_sub"), function ($join) {
                $join->on('sub.promotion_id', '=', 'promotion.promotion_id');
            }, '', '', 'left')->offset($offset)->limit($count);
        $data = $db->select($query_set->toSql(), array_merge($promotion_record->getBindings(), $query_set->getBindings()));
        foreach ($data as $item) {
            $item->order_count = $item->order_count ?: 0;
            $item->commission_total = $item->commission_total ?: '0.00';
        }
        $last_page = ceil($total / $count);
        $result = [
            'page' => [
                'total' => $total,
                'current_page' => $page,
                'last_page' => $last_page,
            ],
            'data' => $data,
        ];
        return $this->output($result);
    }

    /**
     * 推广员累积的客户列表
    */
    public function getCustomer()
    {
        $userId = $this->member['id'];
        $shopId = $this->shop['id'];
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $sql = PromotionRecord::select(
            'member.nick_name','member.avatar','promotion_record.buy_id',
            \DB::raw('count(hg_promotion_record.buy_id) as total'),
            \DB::raw('sum(hg_promotion_record.deal_money) as total_money')
        )
            ->where(['promotion_record.shop_id'=>$shopId,'promotion_record.state'=>1,'promotion_type'=>'promotion']);
        $member_ids = hg_is_same_member($userId,$shopId);
//        $member_ids = [];
        if($member_ids){
            $sql->whereIn('promotion_record.promotion_id',$member_ids);
        }else{
            $sql->where('promotion_record.promotion_id',$userId);
        }
        $result = $sql->leftJoin('member','promotion_record.buy_id','=','member.uid')
            ->groupBy('promotion_record.buy_id')
            ->paginate($count);
        return $this->listToPage($result);
    }

    /**
     * 推广员客户列表
     */
    public function memberList(){
        $promoter_id = $this->member['id'];
        $state = request('state');
        $shop_id = $this->shop['id'];
        $page = request('page') ?: 1;
        if ($page < 0) {
            $page = 1;
        }
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $offset = ($page - 1) * $count;
        $time = time();
        $member_ids = hg_is_same_member($promoter_id, $shop_id);

//        $query_set = MemberBindPromoter::select(['member_id as max_member_id'])
//            ->selectRaw('max(bind_timestamp) as max_bind_timestamp')
//            ->where(['shop_id' => $shop_id])
//            ->groupBy('member_id');
//
//        if($member_ids && count($member_ids) > 1){
//            $query_set->whereIn('promoter_id',$member_ids);
//        }else{
//            $query_set->where('promoter_id',$promoter_id);
//        }

        $promotion_record = PromotionRecord::selectRaw('count(*) as order_count, sum(deal_money) as order_total, buy_id')
            ->where(['shop_id' => $shop_id, 'state' => 1])
            ->groupBy('buy_id');

        if($member_ids && count($member_ids) > 1){
            $promotion_record->whereIn('promotion_id',$member_ids);
        }else{
            $promotion_record->where('promotion_id',$promoter_id);
        }

        $db = app('db');
        $query_set = MemberBindPromoter::select(['member.nick_name', 'member.avatar', 'sub2.order_count', 'sub2.order_total'])
            ->selectRaw('hg_member_bind_promoter.*')
            ->join('member', function ($join) use ($shop_id) {
                $join->on('member.uid', '=', 'member_bind_promoter.member_id')
                    ->where(['member.shop_id' => $shop_id]);
            }, '', '', 'left')
//            ->join($db->raw("({$query_set->toSql()}) as hg_sub"), function ($join) {
//                $join->on('sub.max_member_id', '=', 'member_bind_promoter.member_id')
//                    ->whereColumn('sub.max_bind_timestamp', '=', 'member_bind_promoter.bind_timestamp');
//            }, '', '', 'inner')
            ->join($db->raw("({$promotion_record->toSql()}) as hg_sub2"), function ($join) {
                $join->on('sub2.buy_id', '=', 'member_bind_promoter.member_id');
            }, '', '', 'left')
//            ->mergeBindings($query_set->getQuery())
            ->mergeBindings($promotion_record->getQuery())
            ->where(['member_bind_promoter.shop_id' => $shop_id, 'is_del' => 0])
            ->orderBy('member_bind_promoter.bind_timestamp', 'desc');
        if ($state) {
            $query_set = $query_set->where(['state' => 1])
                ->whereRaw('(invalid_timestamp >= ' . $time . ' or invalid_timestamp=0)');
        } else {
            $query_set = $query_set->whereRaw('(state = 0 or invalid_timestamp < ' . $time . ' and invalid_timestamp!=0)');
        }
        if($member_ids && count($member_ids) > 1){
            $query_set->whereIn('member_bind_promoter.promoter_id',$member_ids);
        }else{
            $query_set->where('member_bind_promoter.promoter_id',$promoter_id);
        }
        $total = $query_set->count();
        $last_page = ceil($total / $count);
        $query_set = $query_set->offset($offset)->limit($count)->get();
        foreach ($query_set as $item) {
            if ($item->invalid_timestamp != 0 && $item->invalid_timestamp < $time) {
                $item->state = 0;
            }
            $item->bind_time = hg_format_date($item->bind_timestamp);
            $item->invalid_time = $item->invalid_timestamp ? hg_format_date($item->invalid_timestamp) : '--';
            $item->order_count = $item->order_count ?: 0;
            $item->order_total = $item->order_total ?: 0;
        }
        $query_set->makeHidden(['id', 'shop_id', 'created_at', 'updated_at', 'bind_timestamp', 'invalid_timestamp']);
        $data = $query_set->toArray();
        $result = [
            'page' => [
                'total' => $total,
                'current_page' => $page,
                'last_page' => $last_page,
            ],
            'data' => $data,
        ];
        return $this->output($result);
    }

    /**
     * 推广商品列表
    */
    public function promotersContentList()
    {
        $this->validateWith([
            'count' => 'numeric',
            'type' => 'required|alpha_dash|in:column,course,article,audio,video,live,member_card',
            'title' => 'alpha_dash'
        ]);
        $shop_id = $this->shop['id'];

        $member_ids = hg_is_same_member($this->member['id'], $this->shop['id']);
        $pro = Promotion::where(['shop_id' => $shop_id])->whereIn('promotion_id', $member_ids)->where('state', 1)->where('is_delete', 0)->first();
        if (!$pro) {
            $this->error('not-open-promotion');
        }

        $type = request('type');
        $order_by = 'create_time';
        $direction = 'desc';
        $count = request('count') ?: 10;
        $order_by_param = request('order_by');
        if ($order_by_param && in_array($order_by_param, self::ORDER_BY_TYPE)) {
            $direction = strpos($order_by_param, '-') === 0 ? 'desc' : 'asc';
            $order_by = str_replace('-', '', $order_by_param);
        }
        if ($type == 'member_card' && $order_by == 'create_time') {
            $order_by = 'created_at';
        }


        $table = $type;

        switch ($type) {
            case 'column':
                $query_set = Column::where([$table . '.shop_id' => $shop_id, 'state' => 1])
                    ->where($table . '.price', '>', 0);
                $select = [$table . '.indexpic'];
                break;
            case 'course':
                $query_set = Course::where([$table . '.shop_id' => $shop_id, 'state' => 1])
                    ->where($table . '.price', '>', 0);
                $select = [$table . '.indexpic', $table . '.course_type'];
                break;
            case 'member_card':
                $query_set = MemberCard::where([$table . '.shop_id' => $shop_id, 'status' => 1])
                    ->where($table . '.price', '>', 0);
                $select = [];
                break;
            default:
                $table = 'content';
                $query_set = Content::where([$table . '.shop_id' => $shop_id, $table . '.type' => $type])
                    ->where($table . '.price', '>', 0)
                    ->where($table . '.column_id', 0)
                    ->where(function ($query) {
                        $query->where('state', 1)->orWhere('state', 0);
                    })
                    ->where('up_time', '<', time());
                $select = [$table . '.indexpic'];
                break;
        }

        $select_list = [$table . '.hashid as content_id', $table . '.title', $table . '.price', $table . '.sales_total','promotion_rate.promoter_rate', 'promotion_rate.invite_rate'];

        $select_list = array_merge($select_list, $select);
        $query_set = $query_set->select($select_list)->selectRaw('hg_promotion_rate.promoter_rate*price as commission')
            ->selectRaw('hg_promotion_rate.invite_rate*price as invite_commission')
            ->join('promotion_content', function ($join) use ($table, $type) {
                $join->on('promotion_content.shop_id', '=', $table . '.shop_id')
                    ->whereColumn('promotion_content.content_id', $table . '.hashid')
                    ->where('promotion_content.content_type', $type);
            }, '', '', 'inner')
            ->leftJoin('promotion_rate', 'promotion_rate.id', 'promotion_content.promotion_rate_id')
            ->where(['promotion_content.is_participate' => 1]);
        $data = $query_set->orderBy($order_by, $direction)->paginate($count);

        if ($data->items()) {
            foreach ($data->items() as $item) {
                $item->commission = number_format($item->commission / 100, 2, '.', '');
                $item->invite_commission = number_format($item->invite_commission / 100, 2, '.', '');
                $item->type = $type;
                if($type == 'member_card'){
                    $item->indexpic = MemberCard::INDEX_PIC_DEFAULT;
                } else {
                    $item->indexpic = hg_parse_image_link($item->indexpic);
                }
            }
        }

        return $this->output($this->listToPage($data));
    }

    /**
     * 推广订单列表
    */
    public function promotersOrderList()
    {
        $userId = $this->member['id'];
        $shopId = $this->shop['id'];
        $count = request('count') ? intval(request('count')) : self::PAGINATE;

        $sql = PromotionRecord::select(
            \DB::raw('hg_promotion_record.money_percent/100*hg_promotion_record.deal_money as money'),
            'member.nick_name','promotion_record.order_id','promotion_record.money_percent','promotion_record.create_time','promotion_record.state',
            'promotion_record.content_type','promotion_record.content_id','promotion_record.content_title','promotion_id','promotion_record.deal_money as price'
        )
            ->where(['promotion_record.shop_id'=>$shopId]);

        $member_ids = hg_is_same_member($userId,$shopId);
//        $member_ids = [];
        if($member_ids){
            $sql->whereIn('promotion_record.promotion_id',$member_ids);
        }else{
            $sql->where('promotion_record.promotion_id',$userId);
        }
        $result =  $sql->leftJoin('member','promotion_record.buy_id','=','member.uid')
            ->orderByDesc('promotion_record.create_time')
            ->paginate($count);

        if(!$result->isEmpty()){
            foreach($result as $item){
                if(self::CONTENT_COLUMN == $item->content_type){
                    $item->indexpic = $item->belongsColumn ? hg_unserialize_image_link($item->belongsColumn->indexpic) : [];
//                    $item->price = $item->belongsColumn ? $item->belongsColumn->price : 0;
                }elseif(self::CONTENT_COURSE == $item->content_type){
                    $item->indexpic = $item->belongsCourse ? hg_unserialize_image_link($item->belongsCourse->indexpic) : [];
                    $item->price = $item->belongsCourse ? $item->belongsCourse->price : 0;
                }elseif (self::CONTENT_MEMBER_CARD == $item->content_type) {
                    $item->indexpic = MemberCard::INDEXPIC;
                    $item->price = $item->belongsMemberCard ? $item->belongsMemberCard->price : 0;
                }
                else{
                    $item->indexpic = $item->belongsContent ? hg_unserialize_image_link($item->belongsContent->indexpic) : [];
//                    $item->price = $item->belongsContent ? $item->belongsContent->price : 0;
                }
                $item->state = intval($item->state);
                $item->money = round($item->money,2);
            }
        }
        return $this->listToPage($result);
    }

    /**
     *
     * @return array
     */
    public function recordList()
    {
        $this->validateWith([
            'type' => 'required|alpha_dash|in:promoter,invite'
        ]);
        $member_id = $this->member['id'];
        $shop_id = $this->shop['id'];
        $count = request('count') ?: 10;
        $type = request('type') == 'promoter' ? 'promotion' : 'visit';

        $query_set = PromotionRecord::select(['promotion_record.promoter_commission as commission', 'promotion_record.money_percent as rate', 'promotion_record.state', 'promotion_record.create_time',
            'promotion_record.order_id', 'member.nick_name', 'promotion_record.content_id', 'promotion_record.content_type', 'promotion_record.content_title', 'order.content_indexpic', 'promotion_record.deal_money as price'])
            ->where(['promotion_record.shop_id' => $shop_id, 'state' => 1, 'promotion_type' => $type]);
        $member_ids = hg_is_same_member($member_id, $shop_id);
        if (count($member_ids) > 1) {
            $query_set = $query_set->whereIn('promotion_record.promotion_id', $member_ids);
        } else {
            $query_set = $query_set->where('promotion_record.promotion_id', $member_id);
        }
        $data = $query_set->leftJoin('member', 'promotion_record.buy_id', 'member.uid')
            ->leftJoin('order', 'promotion_record.order_id', 'order.order_id')
            ->orderBy('promotion_record.create_time', 'desc')
            ->paginate($count);
        return $this->output($this->listToPage($data));
    }

    /**
     * 推广员收益记录
    */
    public function promotersProfit()
    {
        $member_id = $this->member['id'];
        $shop_id = $this->shop['id'];
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $query_set = PromotionRecord::select('promotion_record.promotion_id', 'promotion_record.buy_id',
            'promotion_record.deal_money', 'promotion_record.money_percent', 'promotion_record.visit_percent',
            'promotion_record.finish_time', 'promotion_record.promotion_type', 'member.nick_name as name')
            ->join('member', function ($join) {
                $join->on('member.uid', '=', 'promotion_record.buy_id')
                    ->whereColumn('promotion_record.shop_id', 'promotion_record.shop_id');
            }, '', '', 'left')
            ->where(['promotion_record.shop_id' => $shop_id, 'promotion_record.state' => 1])
            ->orderBy('promotion_record.finish_time', 'desc');
        $member_ids = hg_is_same_member($member_id, $shop_id);
        if (count($member_ids) > 1) {
            $query_set = $query_set->whereIn('promotion_record.promotion_id', $member_ids);
        } else {
            $query_set = $query_set->where('promotion_record.promotion_id', $member_id);
        }

        //获取所赚取的所有佣金
        if (request('cash')) {
            $result = $query_set->get();
        } else {
            $result = $query_set->paginate($count);
        }
        $cash = 0; //获取佣金总额
        if (!$result->isEmpty()) {
            foreach ($result as $value) {
                $isFriendPromoter = false;
                $money = floatval($value->deal_money);
                //推广的佣金
                $profit = $value->money_percent / 100 * $money;
                if ($value->promotion_type == 'visit') {
                    $isFriendPromoter = true;
                }
                $cash += $profit;
                $value->setAttribute('money', round($profit, 2));
                $value->setAttribute('is_friend_promoter', $isFriendPromoter);
            }
        }
        if (request('cash')) {
            //佣金这边缺少减去提现过的金额
            $withDrawMoney = $cash - $this->withDrawTotalMoney();
            return $this->output(['cash' => round($withDrawMoney, 2), 'money' => round($cash, 2)]);
        }
        return $this->listToPage($result);
    }

    /**
     * 提现了多少钱
    */
    private function withDrawTotalMoney()
    {
        $userId = $this->member['id'];
        return Cash::where('member_id',$userId)->sum('cash');
    }

    /**
     * 提现记录
    */
    public function withDrawMoneyRecord()
    {
        $userId = $this->member['id'];
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $result = Cash::select('cash','cash_time')
            ->where('member_id',$userId)
            ->orderBy('cash_time','desc')
            ->paginate($count);
        return $this->listToPage($result);
    }

    /**
     * 统计
    */
    public function statistics()
    {
        $member_id = $this->member['id'];
        $shop_id = $this->shop['id'];
        $member_ids = hg_is_same_member($member_id, $shop_id);
        if($member_ids && count($member_ids) > 1){
            $customerCount = MemberBindPromoter::where(['shop_id' => $shop_id, 'is_del' => 0])
                ->whereIn('promoter_id',$member_ids)->distinct()->count('member_id');
            //成功邀请总数
            $visitCount = Promotion::where([
                    'shop_id' => $shop_id,
                    'state' => 1]
            )->whereIn('visit_id',$member_ids)->count();
            //推广订单总数
            $orderCount = PromotionRecord::where([
                'shop_id' => $shop_id,
                'state' => 1,
            ])->whereIn('promotion_id',$member_ids)->count();
        }else {
            $customerCount = MemberBindPromoter::where(['shop_id' => $shop_id, 'promoter_id' => $member_id, 'is_del' => 0])
                ->distinct()->count('member_id');
            //成功邀请总数
            $visitCount = Promotion::where([
                    'shop_id' => $shop_id,
                    'visit_id' => $member_id,
                    'state' => 1]
            )->count();
            //推广订单总数
            $orderCount = PromotionRecord::where([
                'promotion_id' => $member_id,
                'shop_id' => $shop_id,
                'state' => 1,
                'promotion_type' => 'promotion'
            ])->count();
        }
        $data = [
            'customer_count' => $customerCount,
            'visit_count' => $visitCount,
            'order_count' => $orderCount
        ];
        Cache::forget('promoter:check:state:'.$this->member['id'],0);
        return $this->output($data);
    }

    /**
     * 获取会员可提现金额
     */
    public function withdrawMoney(){

        $url = config('define.order_center.api.m_withdraw_money_combine');
        $query = [
            'uids' => $this->getMemberUids(),
        ];
        $data = $this->init_client($url,[],['query'=>$query],'GET');
        $return = isset($data['result']) ? $data['result'] : [];
        $response = [
            'available'  => isset($return['available']) ? round($return['available'] / 100,2) : 0.00,   //可提现金额
            'pending'    => isset($return['pending']) ? round($return['pending'] / 100,2) : 0.00,       //提现中金额
            'confirmed'  => isset($return['confirmed']) ? round($return['confirmed'] / 100,2) : 0.00,   //已提现金额
            'settling'   => isset($return['settling']) ? round($return['settling'] / 100,2) : 0.00,   //结算中金额
            'income'     => isset($return['income']) ? round($return['income'] / 100,2) : 0.00,         //我的金额
            'total'      => isset($return['total']) ? round($return['total'] / 100,2) : 0.00,           //我的总收入
            'distribute_available'      => isset($return['distribute_available']) ? round($return['distribute_available'] / 100,2) : 0.00,           //分销可提现金额
            'distribute_settling'      => isset($return['distribute_settling']) ? round($return['distribute_settling'] / 100,2) : 0.00,           //分销结算中
            'distribute_income'      => isset($return['distribute_income']) ? round($return['distribute_income'] / 100,2) : 0.00,           //我的分销金额
            'distribute_total'      => isset($return['distribute_total']) ? round($return['distribute_total'] / 100,2) : 0.00,           //分销总收入
        ];
        return $this->output($response);

    }

    /**
     * 获取会员关联的账户信息
     * @return array|string
     */
    private function getMemberUids(){
        $get_route = ['withdrawMoney','getWithdrawRecord'];
        $uids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if(!$uids){
            $uids = [$this->member['id']];
        }
        if(in_array(Route::currentRouteName(),$get_route)){
            $return = implode(',',$uids);
        }else{
            $return = $uids;
        }
        return $return;
    }

    /**
     * 会员提现 提交提现工单
     */
    public function withdraw(){

//        $this->error('withdraw-maintenance');
        
        $this->validateWithAttribute([
            'amount'    => 'required|numeric'
        ]);
        if($this->member['source'] == 'applet'){
            $mobile = Member::where('uid',$this->member['id'])->value('mobile');
            $h5_member = Member::where(['shop_id'=>$this->shop['id'],'mobile'=>$mobile,'source'=>'wechat'])->first();
            if(!$h5_member){
                $this->error('business-error');
            }
        }
        $url = config('define.order_center.api.m_withdraw_workorders_combine');
        $param = [
            'amount'    => request('amount') ? round(request('amount') * 100) : 0,
//            'remark'    => trim(request('remark')),
            'remark'    => '推广员提现',
            'open_id'    => $this->getOpenid(),
            'uids'      => $this->getMemberUids()
        ];
        $data = $this->init_client($url,$param,[],'POST');
        $return = $data['result'];
        return $this->output($return);
    }

    private function getOpenid(){
        if($this->member['source'] == 'applet') {
            $member_ids = hg_is_same_member($this->member['id'], $this->shop['id']);
            $member = Member::whereIn('uid',$member_ids)->where(['source'=>'wechat'])->where('openid','!=','')->where('union_id','!=','')->orderByDesc('create_time')->first();
            return $member->openid ? : '';
        }
        return $this->member['openid'];
    }

    /**
     * 请求封装
     * @param $url
     * @param array $raw_data
     * @param array $query
     * @param string $method
     * @return array
     */
    private function init_client($url,$raw_data=[],$query=[],$method='POST')
    {
        $client = hg_verify_signature($raw_data,time(),'','',$this->member['id']);
        $return = [];
        try{
            $response = ($client->request($method,$url,$query))->getBody()->getContents();
            $return = json_decode($response,1);
            event(new CurlLogsEvent(json_encode($return),$client,$url));
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
            $this->errorWithText($exception->getCode(),$exception->getMessage());
        }
        if($return && $return['error_code']){
            if($return['error_code'] == 4102){//账号不存在时
                return [];
            }
            $this->errorWithText($return['error_code'],$return['error_message'] ? : 'curl 错误');
        }
        return $return ? : [] ;
    }


    /**
     * 获取提现记录
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWithdrawRecord(){
        $this->validateWithAttribute([
            'page'  => 'required|numeric',
            'count'  => 'required|numeric',
        ],[
            'page'      => '页数',
            'count'     => '一页的数据量',
        ]);
        $url = config('define.order_center.api.m_withdraw_record_combine');
        $param = [
            'page'  => intval(request('page')) ? : 1,
            'size'  => intval(request('count')) ? : 10,
            'uids'  => $this->getMemberUids(),
        ];
        $return = $this->init_client($url,[],['query'=>$param],'GET');
        $total = isset($return['result']['count']) ? $return['result']['count'] : 0;
        $return_data = [];
        if(isset($return['result']['data']) && is_array($return['result']['data'])){
            foreach ($return['result']['data'] as $item){
                $item['amount'] = $item['amount'] ? round($item['amount'] / 100,2) : 0.00;
                $item['receipt_amount'] = $item['receipt_amount'] ? round($item['receipt_amount'] / 100,2) : 0.00;
                $return_data[] = $item;
            }
        }
        $response = [
            'page'  => [
                'total'         => $total,
                'current_page'  => intval(request('page')),
                'last_page'     => ceil($total/request('count'))
            ],
            'data'  => $return_data
        ];
        return $this->output($response);

    }

    /**
     * 获取提现记录详情
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWithdrawRecordDetail($id){
        $url = str_replace('{id}',$id,config('define.order_center.api.m_withdraw_record_detail'));
        $return = $this->init_client($url,[],[],'GET');
        if($return){
            $return['create_time'] = $return['create_time'] ? hg_format_date($return['create_time']) : '';
            $return['amount'] = round($return['amount'] / 100 ,2);
            $return['receipt_amount'] = round($return['receipt_amount'] / 100 ,2);
        }
        return $this->output($return);
    }

    /**
     * 用户绑定推广员(解绑之前的推广员)
     * 在有效时间内会员购买任意推广商品成功后该推广员都可以获得分成
     */
    public function memberBindPromoter()
    {
        $this->validateWithAttribute([
            'promoter_id' => 'required|alpha_dash|max:64',
        ], [
            'promoter_id' => '推广员id',
        ]);
        $shop_id = $this->shop['id'];
        $member_uid = $this->member['id'];
        $promoter_id = request('promoter_id');
        $promoter_member_ids = hg_is_same_member($promoter_id, $shop_id);
        if ($member_uid && $promoter_id && !in_array($member_uid, $promoter_member_ids)) {
            $member = Member::where(['shop_id' => $shop_id, 'uid' => $member_uid])->first();
            $promoter = Promotion::where(['shop_id' => $shop_id, 'state' => 1, 'is_delete' => 0])
                ->whereIn('promotion_id', $promoter_member_ids)
                ->first();
            if ($member && $promoter) {
                MemberBindPromoter::bindPromoter($shop_id, $member->uid, $promoter, $promoter_member_ids);
            }
        }
        return $this->output(['success' => 1]);
    }
}