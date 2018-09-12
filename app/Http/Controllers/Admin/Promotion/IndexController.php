<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/25
 * Time: 上午10:42
 */
namespace App\Http\Controllers\Admin\Promotion;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Member;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionContent;
use App\Models\PromotionRecord;
use App\Models\PromotionShop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class IndexController extends BaseController
{

    const ORDER_BY_TYPE = ['order_count', 'order_total', '-order_count', '-order_total'];

    /**
     * 审核列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
//    public function checkList()
//    {
//        $this->validateWith([
//            'count'       => 'numeric',
//            'state'       => 'numeric|in:0,2',
//            'start_time'  => 'date',
//            'end_time'    => 'date',
//            'mobile'      => 'numeric'
//        ]);
//        //$this->shop['id'] = 'AjvVMVNMY39p';
//        $count = request('count') ? : 10;
//        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
//        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
//        $promotion = Promotion::select('promotion.id','promotion.shop_id','promotion.promotion_id','promotion.visit_id','promotion.state','promotion.apply_time','member.avatar','member.mobile','member.nick_name')
//            ->where(['promotion.shop_id'=>$this->shop['id'],'promotion.is_delete'=>0])
//            ->whereIn('promotion.state', [0,2])
//            ->whereBetween('promotion.apply_time',[$start_time,$end_time])
//            ->join('member','member.uid','=','promotion.promotion_id');
//        array_key_exists('state',request()->input()) && $promotion->where('promotion.state',request('state'));
//        request('mobile') && $promotion->where('member.mobile','like','%'.request('mobile').'%');
//        $data = $promotion->orderByDesc('apply_time')->paginate($count);
//        if ($data->items()) {
//            foreach ($data->items() as $item) {
//                $item->apply_time = hg_format_date($item->apply_time);
//                $item->visit_name = $item->visit_id ? ($item->belongsVisit ? $item->belongsVisit->nick_name : '') : '';
//            }
//        }
//        return $this->output($this->listToPage($data));
//    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkNumber()
    {
        $shop_id = $this->shop['id'];
        $count = Promotion::where(['shop_id' => $shop_id, 'state' => 0])->count();
        $result = ['number' => $count];
        return $this->output($result);
    }

    /**
     * 审核列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkList()
    {
        $this->validateWith([
            'count'       => 'numeric',
            'state'       => 'numeric|in:0,2',
            'start_time'  => 'date',
            'end_time'    => 'date',
            'mobile'      => 'numeric'
        ]);
        $order_by = 'promotion.apply_time';
        $direction = 'desc';
        $order_by_param = request('order_by');
        if ($order_by_param && in_array($order_by_param, self::ORDER_BY_TYPE)) {
            $direction = strpos($order_by_param, '-') === 0 ? 'desc' : 'asc';
            $order_by = str_replace('-', '', $order_by_param);
            $order_by = 'sub.' . $order_by;
        }
        $shop_id = $this->shop['id'];
        $count = request('count') ? : 10;
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $db = app('db');
        //订单数和消费金额
        $query_set = Order::where(['shop_id' => $shop_id, 'pay_status' => 1])
            ->selectRaw('user_id, count(id) as order_count, sum(price) as order_total')
            ->groupBy('user_id');
        $promotion = Promotion::select('promotion.id','promotion.shop_id','promotion.promotion_id','promotion.visit_id',
            'promotion.state','promotion.apply_time','member.avatar','member.mobile','member.nick_name','member.source',
            'sub.order_count', 'sub.order_total')
            ->join('member','member.uid','=','promotion.promotion_id')
            ->join($db->raw("({$query_set->toSql()}) as hg_sub"), function ($join) {
                $join->on('sub.user_id', '=', 'promotion.promotion_id');
            }, '', '', 'left')->mergeBindings($query_set->getQuery())
            ->where(['promotion.shop_id'=>$shop_id,'promotion.is_delete'=>0])
            ->whereIn('promotion.state', [0,2])
            ->whereBetween('promotion.apply_time',[$start_time,$end_time]);
        array_key_exists('state',request()->input()) && $promotion->where('promotion.state',request('state'));
        request('mobile') && $promotion->where('member.mobile','like','%'.request('mobile').'%');
        $data = $promotion->orderBy($order_by, $direction)->paginate($count);
        if ($data->items()) {
            foreach ($data->items() as $item) {
                $item->apply_time = hg_format_date($item->apply_time);
                $item->visit_name = $item->visit_id ? ($item->belongsVisit ? $item->belongsVisit->nick_name : '') : '';
            }
        }
        return $this->output($this->listToPage($data));
    }

    /**
     * 审核
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check()
    {
        $this->validateWith([
           'promotion_id' => 'required|alpha_dash',
            'state'       => 'required|numeric|in:0,1'
        ]);
        $update = ['state'    => request('state')];
        if(request('state') == 1) {
            $update['add_time'] = time();
            Cache::forever('promoter:check:state:'.request('promotion_id'),1);
        }
        Promotion::where(['shop_id'=>$this->shop['id'],'is_delete'=>0,'promotion_id'=>request('promotion_id')])->update($update);
        return $this->output(['success'=>1]);
    }

    /**
     * 推广员列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
//    public function listPromotion()
//    {
//        $this->validateWith([
//            'count'       => 'numeric',
//            'start_time'  => 'date',
//            'end_time'    => 'date',
//            'mobile'      => 'numeric',
//        ]);
//        $order = 'apply_time';
//        $direction = 'desc';
//        $count = request('count') ? : 10;
//        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
//        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
//        $promotion = Promotion::select('promotion.id','promotion.shop_id','promotion.promotion_id','promotion.visit_id',
//            'promotion.state','promotion.apply_time','promotion.add_time','member.avatar','member.mobile','member.nick_name')
//            ->where(['promotion.shop_id'=>$this->shop['id'],'promotion.is_delete'=>0,'promotion.state'=>1])
//            ->whereBetween('promotion.add_time',[$start_time,$end_time])
//            ->join('member','member.uid','=','promotion.promotion_id');
//        request('mobile') && $promotion->where('member.mobile','like','%'.request('mobile').'%');
//        $data = $promotion->orderByDesc('apply_time')->paginate($count);
//        //交易数量
//        $deal_count = PromotionRecord::where('shop_id',$this->shop['id'])
//            ->where('state','=',1)//关闭的不算在记录
//            ->select(DB::raw('count(id) as nums,promotion_id'))
//            ->groupBy('promotion_id')
//            ->pluck('nums','promotion_id')
//            ->toArray();
//        //交易金额
//        $deal_money = PromotionRecord::where('shop_id',$this->shop['id'])
//            ->where('state','!=',2)
//            ->select(DB::raw('sum(deal_money) as deal_money,promotion_id'))
//            ->groupBy('promotion_id')
//            ->pluck('deal_money','promotion_id')
//            ->toArray();
//        if ($data->items()) {
//            foreach ($data->items() as $item) {
//                $item->apply_time = hg_format_date($item->apply_time);
//                $item->add_time = hg_format_date($item->add_time);
//                $item->visit_name = $item->visit_id ? ($item->belongsVisit ? $item->belongsVisit->nick_name : '') : '';
//                $item->deal_count = array_key_exists($item->promotion_id,$deal_count) ? (int)$deal_count[$item->promotion_id] : 0;
//                $item->deal_money = array_key_exists($item->promotion_id,$deal_money) ? (float)$deal_money[$item->promotion_id] : 0;
//            }
//        }
//        return $this->output($this->listToPage($data));
//    }

    /**
     * 推广员列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listPromotion()
    {
        $this->validateWith([
            'count'       => 'numeric',
            'start_time'  => 'date',
            'end_time'    => 'date',
            'mobile'      => 'numeric',
            'is_delete'   => 'numeric|in:0,1'
        ]);
        $order_by = 'promotion.apply_time';
        $direction = 'desc';
        $order_by_param = request('order_by');
        if ($order_by_param && in_array($order_by_param, self::ORDER_BY_TYPE)) {
            $direction = strpos($order_by_param, '-') === 0 ? 'desc' : 'asc';
            $order_by = str_replace('-', '', $order_by_param);
            $order_by = 'sub.' . $order_by;
        }
        $shop_id = $this->shop['id'];
        $count = request('count') ? : 10;
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $is_delete = request('is_delete');

        $db = app('db');
        //订单数和成交金额
        $query_set = PromotionRecord::where(['shop_id' => $shop_id, 'state' => 1])
            ->selectRaw('promotion_id, count(id) as order_count, sum(deal_money) as order_total')
            ->groupBy('promotion_id');
        $promotion = Promotion::select('promotion.id','promotion.shop_id','promotion.promotion_id','promotion.visit_id',
            'promotion.state', 'promotion.apply_time', 'promotion.add_time', 'member.avatar', 'member.mobile', 'member.nick_name', 'member.source',
            'sub.order_count', 'sub.order_total')
            ->join('member','member.uid','=','promotion.promotion_id')
            ->join($db->raw("({$query_set->toSql()}) as hg_sub"), function ($join) {
                $join->on('sub.promotion_id', '=', 'promotion.promotion_id');
            }, '', '', 'left')->mergeBindings($query_set->getQuery())
            ->where(['promotion.shop_id'=>$shop_id])
            ->whereBetween('promotion.add_time',[$start_time,$end_time]);
        request('mobile') && $promotion->where('member.mobile','like','%'.request('mobile').'%');
        if (array_key_exists('is_delete', request()->input())) {
            $promotion->where('promotion.is_delete', $is_delete);
        } else {
            $promotion->where(['promotion.is_delete' => 0, 'promotion.state' => 1]);
        }
        $data = $promotion->orderBy($order_by, $direction)->paginate($count);
        if ($data->items()) {
            foreach ($data->items() as $item) {
                $item->apply_time = hg_format_date($item->apply_time);
                $item->add_time = hg_format_date($item->add_time);
                $item->visit_name = $item->visit_id ? ($item->belongsVisit ? $item->belongsVisit->nick_name : '') : '';
                $item->order_count = $item->order_count ? : 0;
                $item->order_total = $item->order_total ? : 0;
            }
        }
        return $this->output($this->listToPage($data));
    }

    /**
     * 推广员清退
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePromotion()
    {
        $this->validateWith([
            'promotion_id' => 'required|alpha_dash'
        ]);
        Promotion::where(['shop_id'=>$this->shop['id'],'is_delete'=>0,'state'=>1,'promotion_id'=>request('promotion_id')])->update(['is_delete'=>1,'delete_time'=>time()]);
        return $this->output(['success'=>1]);
    }

    /**
     * 推广员激活
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function activePromotion()
    {
        $this->validateWith([
            'promotion_id' => 'required|alpha_dash'
        ]);
        Promotion::where(['shop_id'=>$this->shop['id'],'is_delete'=>1,'state'=>1,'promotion_id'=>request('promotion_id')])->update(['is_delete'=>0,'delete_time'=>0]);
        return $this->output(['success'=>1]);
    }


    /**
     * 推广纪录
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordPromotion()
    {
        $this->validateWith([
            'count'       => 'numeric',
            'start_time'  => 'date',
            'end_time'    => 'date',
            'state'       => 'numeric|in:0,1,2',
            'mobile'      => 'numeric',
        ]);
        $count = request('count') ? : 10;
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $promotion_id = [];
        if(request('mobile')){
            $promotion_id = Member::where(['shop_id'=>$this->shop['id']])->where('member.mobile','like','%'.request('mobile').'%')->pluck('uid');
        }
        $shop_id = $this->shop['id'];
        $record = PromotionRecord::select(['order.center_order_no', 'promotion_record.content_title', 'buyer.nick_name as buyer_nick_name'
            ,'order.price','promoter.nick_name as promoter_nick_name', 'promoter.mobile as promoter_mobile',
            'visit.nick_name as visit_nick_name', 'visit.mobile as visit_mobile',
            'promotion_record.money_percent','promotion_record.visit_id', 'promotion_record.visit_percent', 'promotion_record.state', 'promotion_record.create_time'])
            ->where(['promotion_record.shop_id'=>$shop_id, 'promotion_record.promotion_type'=>'promotion'])
            ->leftJoin('member as promoter','promoter.uid', 'promotion_record.promotion_id')
            ->leftJoin('member as visit','visit.uid', 'promotion_record.visit_id')
            ->leftJoin('member as buyer','buyer.uid', 'promotion_record.buy_id')
            ->leftJoin('order','order.order_id', 'promotion_record.order_id')
            ->whereBetween('promotion_record.create_time',[$start_time,$end_time]);
        array_key_exists('state',request()->input()) && $record->where('state',request('state'));
        $promotion_id && $record->whereIn('promotion_id',$promotion_id);
        $data = $record->orderByDesc('promotion_record.create_time')->paginate($count);
        if ($data->items()) {
            foreach ($data->items() as $item) {
                $item->create_time = hg_format_date($item->create_time);
                $item->money = round($item->price * $item->money_percent / 100,2);
                $item->state = intval($item->state);
                if($item->visit_id){
                    $item->visit_money = round($item->price * $item->visit_percent / 100,2);
                }
            }
        }
        return $this->output($this->listToPage($data));
    }

    /**
     * 推广纪录导出excel
     */
    public function recordExcel()
    {
        $flag = ['未结算','已结算','已关闭'];
//        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
//        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
//        $record = PromotionRecord::select('promotion_record.*','member.avatar','member.nick_name','member.mobile')
//            ->where('promotion_record.shop_id',$this->shop['id'])
//            ->whereBetween('promotion_record.create_time',[$start_time,$end_time])
//            ->join('member','member.uid','=','promotion_record.promotion_id');
//        array_key_exists('state',request()->input()) && $record->where('state',request('state'));
//        request('mobile') && $record->where('member.mobile','like','%'.request('mobile').'%');
//        $data = $record->orderByDesc('promotion_record.create_time')->get();

//        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
//        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
//        $promotion_id = [];
//        if(request('mobile')){
//            $promotion_id = Member::where(['shop_id'=>$this->shop['id']])->where('member.mobile','like','%'.request('mobile').'%')->pluck('uid');
//        }
//
//        $record = PromotionRecord::where('promotion_record.shop_id',$this->shop['id'])
//            ->whereBetween('promotion_record.create_time',[$start_time,$end_time]);
//        array_key_exists('state',request()->input()) && $record->where('state',request('state'));
//        request('type') && $record->where('promotion_type',request('type'));
//        $promotion_id && $record->whereIn('promotion_id',$promotion_id);
//        $record = $record->leftJoin()
//        $data = $record->orderByDesc('promotion_record.create_time')->get();
//
//        $result[] = ['序号', '交易时间', '订单号', '商品名称', '成交金额', '推广员昵称'
//            , '推广员手机号', '推广比例', '推广佣金', '邀请方手机号', '邀请比例', '邀请佣金', '状态'];
//
//        if (!$data->isEmpty()) {
//            foreach ($data as $item) {
//                $member = Member::where(['uid'=>$item->promotion_id])->first(['nick_name','mobile']);
//                $nick_name = $this->filter_emoji_text($member? $member->nick_name:'');
//                $result[] = [
//                    'index' => count($result),
//                    'create_time' => hg_format_date($item->create_time),
//                    'order_id' => hg_format_date($item->order_id),
//                    'content_title' => $item->content_title,
//                    'deal_money' => $item->deal_money,
//                    'nick_name' => $nick_name,
//                    'mobile' => $member? $member->mobile:'',
//                    'money_percent' => $item->money_percent.'%',
//                    'money' => round($item->deal_money * $item->money_percent / 100,2),
//                    'state' => $flag[$item->state]
//                ];
//            }
//        }
//        Excel::create(date('Y-m-d',time()).'推广纪录',function ($excel) use($result) {
//            $excel->sheet('record', function($sheet) use($result) {
//                $sheet->fromArray($result,null,'A1',true,false);
//            });
//        })->download('xls');

        $this->validateWith([
            'count'       => 'numeric',
            'start_time'  => 'date',
            'end_time'    => 'date',
            'state'       => 'numeric|in:0,1,2',
            'mobile'      => 'numeric',
        ]);
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $promotion_id = [];
        if(request('mobile')){
            $promotion_id = Member::where(['shop_id'=>$this->shop['id']])->where('member.mobile','like','%'.request('mobile').'%')->pluck('uid');
        }
        $shop_id = $this->shop['id'];
        $record = PromotionRecord::select(['order.center_order_no', 'promotion_record.content_title', 'buyer.nick_name as buyer_nick_name'
            , 'order.price', 'promoter.nick_name as promoter_nick_name', 'promoter.mobile as promoter_mobile',
            'invite.nick_name as invite_nick_name', 'invite.mobile as invite_mobile', 'promotion_record.money_percent',
            'promotion_record.visit_id', 'promotion_record.visit_percent', 'promotion_record.state', 'promotion_record.create_time',
            'promotion_record.promoter_commission', 'promotion_record.invite_commission', 'promotion_record.deal_money'])
            ->where(['promotion_record.shop_id' => $shop_id, 'promotion_record.promotion_type' => 'promotion'])
            ->leftJoin('member as promoter', 'promoter.uid', 'promotion_record.promotion_id')
            ->leftJoin('member as invite', 'invite.uid', 'promotion_record.visit_id')
            ->leftJoin('member as buyer', 'buyer.uid', 'promotion_record.buy_id')
            ->leftJoin('order', 'order.order_id', 'promotion_record.order_id')
            ->whereBetween('promotion_record.create_time', [$start_time, $end_time]);
        array_key_exists('state',request()->input()) && $record->where('state',request('state'));
        $promotion_id && $record->whereIn('promotion_id',$promotion_id);
        $data = $record->orderByDesc('promotion_record.create_time')->get();
        $result[] = ['序号', '交易时间', '订单号', '商品名称', '成交金额', '推广员昵称'
            , '推广员手机号', '推广比例', '推广佣金', '邀请方昵称', '邀请方手机号', '邀请比例', '邀请佣金', '状态'];
        if (!$data->isEmpty()) {
            foreach ($data as $item) {
                $result[] = [
                    'index' => count($result),
                    'create_time' => hg_format_date($item->create_time),
                    'order_id' => $item->center_order_no,
                    'content_title' => $item->content_title,
                    'deal_money' => $item->deal_money,
                    'promoter_nick_name' => $this->filter_emoji_text($item->promoter_nick_name),
                    'promoter_mobile' => $item->promoter_mobile,
                    'promoter_rate' => $item->money_percent . '%',
                    'promoter_commission' => $item->promoter_commission,
                    'invite_nick_name' => $item->visit_id ? $this->filter_emoji_text($item->invite_nick_name) : '--',
                    'invite_mobile' => $item->visit_id ? $item->invite_mobile : '--',
                    'invite_rate' => $item->visit_id ? $item->visit_percent . '%' : '--',
                    'invite_commission' => $item->visit_id ? $item->invite_commission : '--',
                    'state' => $flag[$item->state]
                ];
            }
        }
        Excel::create(date('Y-m-d',time()).'推广纪录',function ($excel) use($result) {
            $excel->sheet('record', function($sheet) use($result) {
                $sheet->fromArray($result,null,'A1',true,false);
            });
        })->download('xls');
    }

    private function filter_emoji_text($text)
    {
        $text = preg_replace_callback('/./u', function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        }, $text);
        return $text;
    }

    /**
     * 业绩统计
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordTotal()
    {
        $this->validateWith([
            'count'       => 'numeric',
            'mobile'      => 'numeric'
        ]);
        $count = request('count') ? : 10;
        $record = $this->orderDate();
        $paginate = PromotionRecord::select('promotion_record.id','promotion_record.promotion_id','member.avatar','member.nick_name','member.mobile')
            ->groupBy('promotion_record.promotion_id')
            ->where(['promotion_record.shop_id'=>$this->shop['id'],'state'=>1])
            ->join('member','member.uid','=','promotion_record.promotion_id');
        request('mobile') && $paginate->where('member.mobile','like','%'.request('mobile').'%');
        $data = $paginate->orderByDesc('promotion_record.create_time')->paginate($count);
        $new = $this->visitData();
        if ($data->items()) {
            foreach ($data->items() as $item) {
                $item->order_nums = array_key_exists($item->promotion_id,$record) ? $record[$item->promotion_id]['order_nums'] : 0;
                $item->order_money = array_key_exists($item->promotion_id,$record) ? $record[$item->promotion_id]['order_money'] : 0;
                $item->visit_nums = array_key_exists($item->promotion_id,$new) ? $new[$item->promotion_id]['visit_nums'] : 0;
                $item->visit_money = array_key_exists($item->promotion_id,$new) ? round($new[$item->promotion_id]['visit_money'],2) : 0;
                $item->total_money = round($item->order_money + $item->visit_money,2);
            }
        }
        return $this->output($this->listToPage($data));
    }

    private function orderDate()
    {
        //纪录总数和钱数
        $record = PromotionRecord::select(DB::raw('count(id) as order_nums,sum(deal_money*money_percent/100) as order_money,promotion_id'))
            ->groupBy('promotion_id')
            ->where(['shop_id'=>$this->shop['id'],'state'=>1,'promotion_type'=>'promotion'])
            ->get();
        $order = [];
        foreach ($record as $k=>$v) {
            if ($v['promotion_id']) {
                $order[$v['promotion_id']] = ['order_nums'=>$v['order_nums'],'order_money'=>round($v['order_money'],2)];
            }
        }
        return $order;
    }

    private function visitData()
    {
        //邀请比例和邀请金
        $visits = PromotionRecord::select(DB::raw('count(id) as visit_nums,sum(deal_money*money_percent/100) as visit_money,promotion_id'))
            ->groupBy('promotion_id')
            ->where(['shop_id'=>$this->shop['id'],'state'=>1,'promotion_type'=>'visit'])
            ->get()
            ->toArray();
        $data = [];
        foreach ($visits as $k=>$v) {
            if ($v['promotion_id']) {
                $data[$v['promotion_id']] = ['visit_nums'=>$v['visit_nums'],'visit_money'=>$v['visit_money']];
            }
        }
        return $data;
    }

    /**
     * 业绩统计导出excel
     */
    public function totalExcel()
    {
        $result[] = ['推广员','手机号码','推广订单','推广订单金额','邀请好友订单','邀请订单金额','合计佣金'];
        $record = $this->orderDate();
        $paginate = PromotionRecord::select('promotion_record.id','promotion_record.promotion_id','member.avatar','member.nick_name','member.mobile')
            ->groupBy('promotion_record.promotion_id')
            ->where(['promotion_record.shop_id'=>$this->shop['id'],'state'=>1])
            ->join('member','member.uid','=','promotion_record.promotion_id');
        request('mobile') && $paginate->where('member.mobile','like','%'.request('mobile').'%');
        $data = $paginate->orderByDesc('promotion_record.create_time')->get();
        $new = $this->visitData();
        $index = 1;
        if (!$data->isEmpty()) {
            foreach ($data as $item) {
                $result[$index] = [
                    'nick_name' => $item->nick_name,
                    'mobile' => $item->mobile,
                    'order_nums' => array_key_exists($item->promotion_id,$record) ? $record[$item->promotion_id]['order_nums'] : 0,
                    'order_money' => array_key_exists($item->promotion_id,$record) ? $record[$item->promotion_id]['order_money'] : 0,
                    'visit_nums' => array_key_exists($item->promotion_id,$new) ? $new[$item->promotion_id]['visit_nums'] : 0,
                    'visit_money' => array_key_exists($item->promotion_id,$new) ? round($new[$item->promotion_id]['visit_money'],2) : 0,
                ];
                $result[$index]['total_money'] = round($result[$index]['order_money']+$result[$index]['visit_money'],2);
                $index++;
            }
        }
        Excel::create(date('Y-m-d',time()).'业绩统计',function ($excel) use($result) {
            $excel->sheet('total', function($sheet) use($result) {
                $sheet->fromArray($result,null,'A1',true,false);
            });
        })->download('xls');
    }


    /**
     * 获取推广员佣金比例配置
     */
    public function rateConfig(){
        $this->validateWithAttribute([
            'distributors_id'   => 'required|array',
            'seller_id'         => 'alpha_dash|max:64',
            'order'             => 'required|array'
        ],[
            'distributors_id'   => '分销员用户 ID 列表',
            'seller_id'         => '卖家id',
            'order'             => '订单中包含的商品信息',
        ]);

        $order_id = Order::where(['center_order_no'=>request('order_no')])->value('order_id');
        if(!$order_id){
            $this->error('no-order');
        }

        $promotion_record = PromotionRecord::where([
            'shop_id'   => request('seller_id'),
            'order_id'  => $order_id,
            'promotion_type'=>'promotion',

        ])->first();

        $return = [];
        if($promotion_record) {
            $content = [
                'product_id'    => $promotion_record->content_id,
                'sku_id'    => $promotion_record->content_type,
            ];
            $content['rate'] = round($promotion_record->money_percent / 100, 2);
            $return[$promotion_record->promotion_id][] = $content;

            if ($promotion_record->visit_id && $promotion_record->visit_id != $promotion_record->promotion_id) {
                $content['rate'] = round($promotion_record->visit_percent / 100, 2);
                $return[$promotion_record->visit_id][] = $content;
            }
        }

//        $content_info = request('order');
//        $return = $content = [];
//        if($content_info && is_array($content_info)){
//            foreach ($content_info as $item) {
//                $promotion_content = PromotionContent::where(['content_id'=>$item['product_id'],'content_type'=>$item['sku_id']])->firstOrFail(['money_percent','visit_percent','shop_id']);
//                if($promotion_content){
//                    $rate = $promotion_content->money_percent == -1 ? PromotionShop::where('shop_id',$promotion_content->shop_id)->value('money_percent') : $promotion_content->money_percent;
//                    $content[] = [
//                        'product_id'    => $item['product_id'],
//                        'sku_id'    => $item['sku_id'],
//                        'rate'      => round($rate/100,2),
//                    ];
//                }
//            }
//        }
//        $member_ids = request('distributors_id');
//        $promotion = [];
//        if($member_ids) {
//            foreach ($member_ids as $promoter_id) {
//                $visit = Promotion::where(['shop_id' => request('seller_id')])->where('promotion_id', $promoter_id)->first(['id', 'visit_id', 'promotion_id']);
//                $member = hg_is_same_member($promoter_id, request('seller_id'));
//                //判断当前是否是推广员,是的话
//                if ($visit) {
//                    $promotion = $visit;
//                } else {
//                    //如果不是推广员，查看绑定同一个手机号的账号是不是推广员
//                    if ($member) {
//                        $other_member = array_diff($member,[$promoter_id]);
//                        $promotion = Promotion::where(['shop_id' => request('seller_id'),'is_delete'=>0,'state'=>1])->whereIn('promotion_id', $other_member)->first(['id', 'visit_id', 'promotion_id']);
//                    }
//                }
//                if ($promotion) {
//                    foreach ($content as $key => $value) {
//
//                        $promotion_record = PromotionRecord::where([
//                            'shop_id'   => request('seller_id'),
//                            'order_id'  => $order_id,
//                            'promotion_type'=>'promotion',
//
//                        ])->first();
//
//                        if($promotion_record) {
//                            $value['rate'] = round($promotion_record->money_percent / 100, 2);
//                            $return[$promotion_record->promotion_id][] = $value;
//
//                            if ($promotion_record->visit_id && $promotion_record->visit_id != $promotion_record->promotion_id) {
//                                $value['rate'] = round($promotion_record->visit_percent / 100, 2);
//                                $return[$promotion_record->visit_id][] = $value;
//                            }
//                        }
//                        $visit_ids = [];
//                        //判断当前是否有推广记录
//                        $promotion_record = PromotionRecord::where([
//                            'shop_id' => request('seller_id'),
//                            'content_id' => $value['product_id'],
//                            'content_type' => $value['sku_id'],
//                            'promotion_type'=>'promotion',
//                            'promotion_id'=>$promotion->promotion_id,
//                        ])
//                            ->first(['promotion_id', 'visit_id']);
//                        $promotion_record && $visit_ids = array_intersect([$promotion_record->visit_id],$member_ids);
//                        if(!$promotion_record){
//                            $promotion_records = PromotionRecord::where([
//                                'shop_id' => request('seller_id'),
//                                'content_id' => $value['product_id'],
//                                'content_type' => $value['sku_id'],
//                                'promotion_type'=>'promotion',
//                            ])
//                                ->whereIn('promotion_id',$member)
//                                ->groupBy('promotion_id')
//                                ->pluck('visit_id','promotion_id');
//                            $promotion_records->isNotEmpty() && $promotion_record = $promotion_records;
//                            $promotion_record && $visit_ids = array_intersect($promotion_record->values()->toArray(),$member_ids);
//
//                        }
//                        //当前推广员的邀请人处理
//                        if ($promotion_record && !empty($visit_ids)) {
//                            $rate = $value;
//                            $rate['rate'] = $rate['visit_rate'];
//                            unset($rate['visit_rate']);
//                            $visit_data = array_fill_keys(($visit_ids),[$rate]);
//                            $return = array_merge($visit_data,$return);
//                        }
//
//                        if (!isset($return[$promoter_id]) && $promotion_record) {
//                            unset($value['visit_rate']);
//                            $return[$promoter_id][] = $value;
//                        }
//                    }
//                }
//            }
//        }
        return response()->json([
            'error_code'     => '0',
            'error_message'   => '',
            'result'    => $return
        ]);
    }
}