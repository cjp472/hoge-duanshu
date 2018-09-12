<?php
namespace App\Http\Controllers\Manage\MemberCard;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\CardRecord;
use App\Models\Manage\MemberCard;

class MemberCardController extends BaseController
{
    /**
     * @return \Illuminate\Http\JsonResponse
     * 会员卡列表
     */
    public function cardLists()
    {
        $lists = MemberCard::orderBy('updated_at','desc')->paginate(request('count')?:10);
        $member_cards = $this->listToPage($lists);
        if($member_cards && $member_cards['data']){
            foreach ($member_cards['data'] as $item){
                $item->up_time = $item->up_time ? hg_format_date($item->up_time) : '';
                $item->expire = $item->expire ? intval($item->expire) : 0;
                $item->status = $item->status ? intval($item->status) : 0;
                $item->style = $item->style ? intval($item->style) : 1;
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
        $this->validateId();
        $member_card = $this->getMemberCardInfo(request('id'));
        $member_card->up_time = $member_card->up_time ? hg_format_date($member_card->up_time) : '';
        $member_card->subscribe = $member_card->record?count($member_card->record):0;
        $member_card->expire = $member_card->expire ? intval($member_card->expire) : 0;
        $member_card->status = $member_card->status ? intval($member_card->status) : 0;
        $member_card->style = $member_card->style ? intval($member_card->style) : 1;
        $member_card->makeHidden(['record']);
        return $this->output($member_card);
    }


    //数据验证
    private function validateId(){
        $this->validateWithAttribute(['id' => 'required|alpha_dash|size:12'],['id'=>'会员卡id']);
    }


    //获取单条会员卡信息
    private function getMemberCardInfo($id){
        return  MemberCard::where(['hashid'=>$id])->first();
    }
    /**
     * @return \Illuminate\Http\JsonResponse
     * 会员卡订购记录
     */
    public function recordLists(){
        $this->validateId();
        $sql= CardRecord::where(['card_id'=>request('id')]);
        request('source') && $sql->where('source',request('source'));
        request('nick_name') && $sql->where('nick_name','like','%'.request('nick_name').'%');
        if(request('start_time')&&!request('end_time')){
            $sql->whereBetween('order_time',[strtotime(request('start_time')),time()]);
        }elseif (request('end_time')&&!request('start_time')){
            $sql->whereBetween('order_time',[0,strtotime(request('end_time'))]);
        }elseif(request('start_time')&&request('end_time')){
            $sql->whereBetween('order_time',[strtotime(request('start_time')),strtotime(request('end_time'))]);
        }
        $state = request('state');
        isset($state) && request('state')== 0 && $sql->where('end_time', '<', time());
        isset($state) && request('state')== 1 && $sql->where('start_time', '<', time())->where('end_time', '>', time());
        $data = $sql->paginate(request('count')?:10);
        $record = $this->listToPage($data);
        if($record && $record['data']){
            foreach ($record['data'] as $item){
                $item->nick_name = $item->nick_name?$item->nick_name:($item->member?$item->member->nick_name:'');
                $item->avatar = $item->member?$item->member->avatar:'';
                $item->source = $item->source?$item->source:($item->member?$item->member->source:'');
                $item->sex = $item->member?$item->member->sex:'';
                if($item->start_time < time() && $item->end_time > time()){
                    $item->state = 1;
                }else{
                    $item->state = 0;
                }
                $item->start_time = $item->start_time?hg_format_date($item->start_time):0;
                $item->end_time = $item->end_time?hg_format_date($item->end_time):0;
                $item->order_time = $item->order_time?hg_format_date($item->order_time):0;
                $item->makeHidden(['member']);
            }
        }
        return $this->output($record);
    }
}