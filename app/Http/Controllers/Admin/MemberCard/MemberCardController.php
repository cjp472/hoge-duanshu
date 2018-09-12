<?php
/**
 * Created by PhpStorm.
 * User: huang an
 * Date: 17/12/21
 * Time: 下午3:09
 */

namespace App\Http\Controllers\Admin\MemberCard;

use App\Http\Controllers\Admin\BaseController;
use App\Models\CardRecord;
use App\Models\MemberCard;
use App\Models\PromotionContent;
use App\Models\PromotionShop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Vinkla\Hashids\Facades\Hashids;

class MemberCardController extends BaseController
{
    /**
     * @return \Illuminate\Http\JsonResponse
     * 会员卡列表
     */
    public function cardLists()
    {
        $lists = MemberCard::where(['shop_id'=>$this->shop['id'],'is_del'=>0])
            ->orderBy('order_id')
            ->orderBy('up_time','desc')
            ->orderBy('updated_at','desc')
            ->orderBy('created_at','desc');

        request('status') && $lists->where('status', request('status'));
        request('title') && $lists->where('title', 'LIKE', '%' . request('title') . '%');
        $lists = $lists->paginate(request('count')?:10);
        
        $member_cards = $this->listToPage($lists);
        if($member_cards && $member_cards['data']){
            foreach ($member_cards['data'] as $item){
                $item->serialize();
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
        $member_card->serialize();
        return $this->output($member_card);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 会员卡订购记录
     */
    public function recordLists(){
        $this->validateId();
        $sql= CardRecord::select('card_record.id', 'card_record.member_id', 'card_record.option','member.avatar','member.nick_name', 'member.source','member.sex', 'card_record.start_time', 'card_record.end_time','card_record.order_id', 'card_record.order_time','card_record.price')
            ->leftJoin('member', 'card_record.member_id', '=', 'member.uid')
            ->where(['card_record.card_id'=>request('id'),'card_record.shop_id'=>$this->shop['id']]);
        $source = request('source');
        if(isset($source) && $source=='h5'){
            $sql->where(function ($query) {
                $query->where('card_record.source',request('source'))->orWhere('card_record.source','wechat');
            });
        }elseif(isset($source)){
            $sql->where('card_record.source',$source);
        }
        request('nick_name') && $sql->where('card_record.nick_name','like','%'.request('nick_name').'%');
        if(request('start_time')&&!request('end_time')){
            $sql->whereBetween('card_record.order_time',[strtotime(request('start_time')),time()]);
        }elseif (request('end_time')&&!request('start_time')){
            $sql->whereBetween('card_record.order_time',[0,strtotime(request('end_time'))]);
        }elseif(request('start_time')&&request('end_time')){
            $sql->whereBetween('card_record.order_time',[strtotime(request('start_time')),strtotime(request('end_time'))]);
        }
        request('channel') && request('channel') == 'code' && $sql->where('card_record.order_id',-1);
        request('channel') && request('channel') == 'order' && $sql->where('card_record.order_id','!=',-1);
        $state = request('state');
        isset($state) && request('state')== 0 && $sql->where('card_record.end_time', '<', time());
        isset($state) && request('state')== 1 && $sql->where('card_record.start_time', '<', time())->where('card_record.end_time', '>', time());
        $data = $sql->paginate(request('count')?:10);
        $record = $this->listToPage($data);
        if($record && $record['data']){
            foreach ($record['data'] as $item){
                $item->nick_name = $item->nick_name;
                $item->avatar = $item->avatar;
                $item->source = $item->source;
                $item->sex = $item->sex;
                $item->optionAndExpire();
                if($item->start_time < time() && $item->end_time > time()){
                    $item->state = 1;
                }else{
                    $item->state = 0;
                }
                $item->start_time = $item->start_time?hg_format_date($item->start_time):0;
                $item->end_time = $item->end_time?hg_format_date($item->end_time):0;
                $item->order_time = $item->order_time?hg_format_date($item->order_time):0;
                if($item->order_id == '-1' || $item->order_id == -1){
                    $item->channel = 'code';
                    $item->price = '0.00';
                }else{
                    $item->channel = 'order';
                }
                $item->makeHidden(['member','order_id', 'order']);
            }
        }
        return $this->output($record);
    }

    /**
     * 会员卡新增
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cardCreate()
    {
        $data = $this->cardValidate();
        $member_card = new MemberCard();
        $member_card->setRawAttributes($data);
        $member_card->save();
        $hashid = Hashids::encode($member_card->id);
        $member_card->hashid = $hashid;
        $member_card->save();
        $this->createPromotionContent($hashid, 'member_card');
        $member_card->serialize();
        return $this->output($member_card);
    }

    /**
     * 会员卡修改
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cardUpdate()
    {   
        $this->validateId();
        $data = $this->cardValidate();
        Cache::forget('member:card:'.$this->shop['id'].':'.request('id'));
        $member_card = $this->getMemberCardInfo(request('id'));
        $member_card->setRawAttributes($data);
        $member_card->save();
        $member_card->hashid = request('id');
        $member_card->serialize();
        return $this->output($member_card);
    }

    /**
     * 会员卡删除
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cardDelete($id)
    {
        $member_card = $this->getMemberCardInfo($id);
        if($member_card){
            $member_card->is_del = 1;
            $member_card->save();
            PromotionContent::where('content_type','member_card')->where('content_id',$id)->delete();
        }else{
            $this->error('NO_MEMBER_CARD');
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 会员卡上下架
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState()
    {
        $this->validateWithAttribute([
            'id' => 'required|alpha_dash|size:12',
            'status' => 'required|numeric|in:1,2'
        ],['id'=>'会员卡id','status'=>'上架状态']);
        $member_card = $this->getMemberCardInfo(request('id'));
        $member_card->status = request('status');
        $member_card->up_time = time();
        $member_card->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 会员卡置顶
     */
    public function cardTop(){
        $this->validateWithAttribute(['id'=>'required','top'=>'required'],['id'=>'会员卡id','top'=>'置顶状态']);
        $member_card = $this->getMemberCardInfo(request('id'));
        $member_card->top = request('top');
        $member_card->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 用户的会员卡订购列表
     */
    public function cardUser(){
        $this->validateWithAttribute(['member_id'=>'required'],['member_id'=>'会员id']);
        $data = CardRecord::where(['shop_id'=>$this->shop['id'],'member_id'=>request('member_id')])->paginate(request('count')?:10);
        $lists = $this->listToPage($data);
        if($lists && $lists['data']){
            foreach($lists['data'] as $item){
                $item->state = $item->start_time < time() && $item->end_time > time() ? 1 : 0;
                $item->start_time = $item->start_time?hg_format_date($item->start_time):0;
                $item->end_time = $item->end_time?hg_format_date($item->end_time):0;
            }
        }

        return $this->output($lists);
    }

    //数据验证
    private function validateId(){
        $this->validateWithAttribute(['id' => 'required|alpha_dash|size:12'],['id'=>'会员卡id']);
    }

    //获取单条会员卡信息
    private function getMemberCardInfo($id){
        return  MemberCard::where(['hashid'=>$id,'shop_id'=>$this->shop['id']])->first();
    }

    //数据验证
    private function cardValidate()
    {   
        $this->validateWithAttribute([
            'title'      => 'required|alpha_dash|max:32',
            'discount'   => 'required|numeric',
            'options'    => 'required|array',
            'options.*.value' => ['required',Rule::in(MemberCard::SPECKEYS),'distinct'],
            'options.*.price' => 'required|numeric',
            'style'      => 'required',
            'use_notice'       => 'max:600',
            'discount_explain' => 'max:600',
            'purchase_notice'  => 'max:600',
            'verbose_title' =>'max:100'
        ],[
            'title'      => '名称',
            'discount'   => '折扣',
            'options'    => '规格',
            'style'      => '风格',
            'verbose_title' =>'描述名'
            // 'options.in' => '规格选项不在'.join(',',MemberCard::SPECKEYS).'中'
        ]);
        foreach (request('options') as $option) {
            if(!validate_price_with_max($option['price'])){
            return $this->error('价格设置错误或超过最大价格限制');
            }
        }
        $data = [
            'shop_id'   => $this->shop['id'],
            'title'     => request('title'),
            'verbose_title' => request('verbose_title'),
            'style'     => request('style'),
            'card_type' => request('card_type') ? : 1,
            'discount'  => request('discount'),
            'options'     => request('options'),
            'use_notice'       => htmlspecialchars_decode(request('use_notice')),
            'discount_explain' => htmlspecialchars_decode(request('discount_explain')),
            'purchase_notice'  => htmlspecialchars_decode(request('purchase_notice')),
            'status'    => request('status') ? : 0,
            'verbose_title' => request('verbose_title')
        ];
        request('status') == 1 && $data['up_time'] = time();
        foreach ($data['options'] as $key => $value) {
            $data['options'][$key]['id'] = $key;
        }
        $data['price'] = $data['options'][0]['price'];
        $data['options'] = serialize($data['options']);
        return $data;
    }


    /**
     * 会员卡排序
     */
    public function cardSort(){

        $this->validateWithAttribute([
            'id'    => 'required|alpha_dash|size:12',
            'order' => 'required|numeric'
        ], [
            'id'    => '会员卡id',
            'order' => '排序位置'
        ]);

        $order_id = MemberCard::where(['shop_id' => $this->shop['id']])
            ->orderBy('order_id')
            ->orderBy('top','desc')
            ->orderBy('updated_at','desc')
            ->pluck('hashid');
        $old_order = MemberCard::where(['hashid'=>request('id')])->firstOrFail() ? MemberCard::where(['hashid'=>request('id')])->firstOrFail()->order_id : (isset(array_flip($order_id->toArray())[request('id')]) ? array_flip($order_id->toArray())[request('id')] +1 : 0);

        hg_sort($order_id,request('id'),request('order'),$old_order,'memberCard');
        return $this->output(['success'=>1]);

    }

    /**
     * 内容不参与会员卡
     */
    public function contentJoin() {
        $this->validateWithAttribute([
            'content_id'    => 'required|alpha_dash',
            'content_type'  => 'required',
            'join'          => 'required|boolean'       
        ], [
            'content_id'    => '内容id',
            'content_type' => '内容类型',
            'join'         => '是否适用'
        ]);

        MemberCard::joinMemberCard(request('content_type'),request('content_id'),$this->shop['id'], request('join'));
        return $this->output(['success'=>1]);
    }
}











