<?php
/**
 * Created by PhpStorm.
 * User: tanqiang
 * Date: 2018/8/20
 * Time: 上午10:01
 */

namespace App\Http\Controllers\Admin\Promotion;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Member;
use App\Models\MemberBindPromoter;
use App\Models\PromotionRecord;

class PromoterController extends BaseController
{

    /**
     * 推广员明细
     */
    public function detail($id)
    {
        $shop_id = $this->shop['id'];
        $member = Member::select(['member.nick_name', 'member.avatar', 'member.mobile', 'promotion.add_time', 'visitor.nick_name as visitor_name'])
            ->where(['member.uid' => $id, 'member.shop_id' => $shop_id])
            ->join('promotion', function ($join) use ($shop_id) {
                $join->on('promotion.promotion_id', 'member.uid')
                    ->where(['promotion.shop_id' => $shop_id]);
            }, '', '', 'left')
            ->join('member as visitor', function ($join) use ($shop_id) {
                $join->on('promotion.visit_id', 'visitor.uid')
                    ->where(['visitor.shop_id' => $shop_id]);
            }, '', '', 'left')
            ->first();

        $promotion_statistics = PromotionRecord::selectRaw('count(*) as order_num, sum(deal_money * money_percent / 100) as commission_total')
            ->where(['shop_id' => $shop_id, 'promotion_id' => $id, 'promotion_type' => 'promotion', 'state' => 1])->first();

        $visit_statistics = PromotionRecord::selectRaw('count(*) as order_num, sum(deal_money * money_percent / 100) as commission_total')
            ->where(['shop_id' => $shop_id, 'promotion_id' => $id, 'promotion_type' => 'visit', 'state' => 1])->first();

        $result = [
            'nick_name' => $member->nick_name,
            'avatar' => $member->avatar,
            'mobile' => $member->mobile,
            'add_time' => hg_format_date($member->add_time),
            'visitor_name' => $member->visitor_name,
            'promoter' => [
                'order_num' => $promotion_statistics ? $promotion_statistics->order_num : 0,
                'commission_total' => $promotion_statistics ? number_format($promotion_statistics->commission_total, 2) : 0,
            ],
            'visitor' => [
                'order_num' => $visit_statistics ? $visit_statistics->order_num : 0,
                'commission_total' => $visit_statistics ? number_format($visit_statistics->commission_total, 2) : 0,
            ],
        ];

        return $this->output($result);
    }

    /**
     * 绑定的会员列表 包括有效和失效
     */
    public function memberList()
    {
        $this->validateWithAttribute([
            'promoter_id' => 'required',
        ], [
            'promoter_id' => '推广员id',
        ]);

        $count = request('count') ?: 10;
        $page = request('page') ?: 1;
        if ($page < 0) {
            $page = 1;
        }
        $id = request('promoter_id');
        $offset = ($page - 1) * $count;
        $shop_id = $this->shop['id'];
//        $query_set = MemberBindPromoter::select(['promoter_id as max_promoter_id', 'member_id as max_member_id'])
//            ->selectRaw('max(bind_timestamp) as max_bind_timestamp')
//            ->where(['shop_id' => $shop_id, 'promoter_id' => $id])
//            ->groupBy('promoter_id')
//            ->groupBy('member_id');

        $promotion_record = PromotionRecord::selectRaw('count(*) as order_count, sum(deal_money) as order_total, promotion_id, buy_id')
            ->where(['shop_id' => $shop_id, 'promotion_id' => $id, 'state' => 1])
            ->groupBy('promotion_id')
            ->groupBy('buy_id');

        $db = app('db');
        $query_set = MemberBindPromoter::select(['member.nick_name', 'member.avatar', 'sub2.order_count', 'sub2.order_total'])
            ->selectRaw('hg_member_bind_promoter.*')
            ->join('member', function ($join) use ($shop_id) {
                $join->on('member.uid', '=', 'member_bind_promoter.member_id')
                    ->where(['member.shop_id' => $shop_id]);
            }, '', '', 'left')
//            ->join($db->raw("({$query_set->toSql()}) as hg_sub"), function ($join) {
//                $join->on('sub.max_promoter_id', '=', 'member_bind_promoter.promoter_id')
//                    ->whereColumn('sub.max_member_id', '=', 'member_bind_promoter.member_id')
//                    ->whereColumn('sub.max_bind_timestamp', '=', 'member_bind_promoter.bind_timestamp');
//            }, '', '', 'inner')
            ->join($db->raw("({$promotion_record->toSql()}) as hg_sub2"), function ($join) {
                $join->on('sub2.promotion_id', '=', 'member_bind_promoter.promoter_id')
                    ->whereColumn('sub2.buy_id', '=', 'member_bind_promoter.member_id');
            }, '', '', 'left')
//            ->mergeBindings($query_set->getQuery())
            ->mergeBindings($promotion_record->getQuery())
            ->where(['member_bind_promoter.shop_id' => $shop_id, 'member_bind_promoter.promoter_id' => $id, 'is_del'=>0])
            ->orderBy('member_bind_promoter.bind_timestamp', 'desc');
        $total = $query_set->count();
        $last_page = ceil($total / $count);
        $query_set = $query_set->offset($offset)->limit($count)->get();
        foreach ($query_set as $item) {
            if ($item->invalid_timestamp != 0 && $item->invalid_timestamp < time()) {
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
     * 推广列表
     */
    public function recordList()
    {
        $this->validateWithAttribute([
            'promoter_id' => 'required',
        ], [
            'promoter_id' => '推广员id',
        ]);

        $count = request('count') ?: 10;
        $shop_id = $this->shop['id'];
        $id = request('promoter_id');

        $query_set = PromotionRecord::select(['order.center_order_no', 'order.order_time', 'member.nick_name',
            'promotion_record.content_title', 'order.price', 'promotion_record.money_percent', 'promotion_record.promotion_type', 'promotion_record.state'])
            ->where(['promotion_record.shop_id' => $shop_id, 'promotion_record.promotion_id' => $id])
            ->join('member', function ($join) use ($shop_id) {
                $join->on('member.uid', '=', 'promotion_record.buy_id')
                    ->where(['member.shop_id' => $shop_id]);
            }, '', '', 'left')
            ->join('order', function ($join) use ($shop_id) {
                $join->on('order.order_id', '=', 'promotion_record.order_id')
                    ->where(['order.shop_id' => $shop_id]);
            }, '', '', 'left')->paginate($count);

        if (!$query_set->isEmpty()) {
            foreach ($query_set as $item) {
                $item->order_time = hg_format_date($item->order_time);
                $item->commission = number_format($item->money_percent * $item->price / 100, 2, '.', '');
            }
        }
        return $this->output($this->listToPage($query_set));
    }


}