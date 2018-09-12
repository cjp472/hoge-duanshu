<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2017/12/25
 * Time: 下午5:46
 */
namespace App\Http\Controllers\H5\MemberCard;

use App\Events\ContentViewEvent;
use App\Http\Controllers\H5\BaseController;
use App\Models\CardRecord;
use App\Models\MemberCard;

class MemberCardController extends BaseController{

    /**
     * @return \Illuminate\Http\JsonResponse
     * 会员卡列表
     */
    public function cardLists(){
        $filters = $this->contentCommonFilters();
        $sql = MemberCard::where(['shop_id'=>$this->shop['id'],'status'=>1,'is_del'=>0]);
        $sql = $this->filterSql($sql, $filters);
        $lists = $sql->orderBy('order_id')
            ->orderBy('top','desc')
            ->orderBy('updated_at','desc')
            ->paginate(request('count')?:10);
        $member_cards = $this->listToPage($lists);
        if($member_cards && $member_cards['data']){
            foreach ($member_cards['data'] as $item){
                $item->up_time = $item->up_time ? hg_format_date($item->up_time) : '';
                $item->expire = $item->expire ? intval($item->expire) : 0;
                $item->status = $item->status ? intval($item->status) : 0;
                $item->style = $item->style ? intval($item->style) : 1;
                $item->options = $item->options ? unserialize($item->options) : [];
                $item->setIndexPic();
            }
        }
        return $this->output($member_cards);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 会员卡详情
     */
    public function cardDetail()
    {
        $this->validateWithAttribute(['id'=>'required'],['id'=>'会员卡id']);
        $member_card = MemberCard::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id']])->first();
        if($member_card){
            $member_card->up_time = $member_card->up_time ? hg_format_date($member_card->up_time) : '';
            $member_card->style = intval($member_card->style);
            $member_card->setIndexPic();
            // $member_card->expire = $member_card->expire ? intval($member_card->expire) : 0;
            $member_card->options = unserialize($member_card->options);
            $member_card->status = $member_card->status ? intval($member_card->status) : 0;
            $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
            if($member_ids){
                $record = CardRecord::whereIn('member_id',$member_ids)->where(['card_id'=>request('id'),'shop_id'=>$this->shop['id']])->where('end_time','>',time())->first();
            }else{
                $record = CardRecord::where(['card_id'=>request('id'),'shop_id'=>$this->shop['id'],'member_id'=>$this->member['id']])->where('end_time','>',time())->first();
            }
            $member_card->is_subscribe = $record ? 1 : 0;   //详情接口返回当前会员是否订购过该会员卡
            $member_card->expire_date_start = $record ? date('Y-m-d',$record->start_time) : 0;   //详情接口返回当前会员订购会员卡到期时间
            $member_card->expire_date_end = $record ? date('Y-m-d',$record->end_time) : 0;
            $member_card->promoter = $this->promoter($this->shop['id'],$this->member['id'],$member_card->hashid,'member_card', $member_card->price);
            if ($member_card->is_del && $member_card->is_subscribe  == 0) {
                return $this->error('NO_MEMBER_CARD'); // 会员卡已被删除,但该会员并没订购过该会员卡
            }
            if ($member_card->status == 2 && $member_card->is_subscribe == 0) {
                return $this->error('off-shelf'); // 会员卡已下架,但该会员并没订购过该会员卡
            }
            $member_card->type = 'member_card';
            event(new ContentViewEvent($member_card, $this->member));
            if ($member_card->is_subscribe == 1) {
                $record->optionAndExpire();
                $member_card->option = $record->option;
                $member_card->expire = $record->expire;
            }
            return $this->output($member_card);
        }else{
            return $this->error('NO_MEMBER_CARD');
        }
    }





}