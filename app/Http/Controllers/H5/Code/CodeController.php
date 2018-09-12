<?php
/**
 * 前端使用邀请码
 */
namespace App\Http\Controllers\H5\Code;

use App\Http\Controllers\H5\BaseController;
use App\Jobs\CodePaymentSave;
use App\Models\CardRecord;
use App\Models\Code;
use App\Models\Content;
use App\Models\Course;
use App\Models\InviteCode;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Payment;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CodeController extends BaseController
{
    /**
     * 分享页面
     * @param $code
     * @return mixed
     */
    public function getShareContent($code)
    {
        $exist = '';
        Code::where('code',$code)->update(['copy'=>0]);
        if($exist){
            $data = json_decode($exist,1);
            $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
//        $mobile = Member::where('uid',$this->member['id'])->value('mobile');
//        $member_ids = Redis::smembers('mobileBind:'.$this->shop['id'].':'.$mobile);
            if($member_ids){
                $data['self_receive'] = in_array(Cache::get('code:use:'.$code),$member_ids) ? 1 : 0;
            }else{
                $data['self_receive'] = $this->member['id'] == Cache::get('code:use:'.$code) ? 1 : 0;
            }
            return $this->output($data);
        }else{
            $code = Code::where('code',$code)
                ->leftJoin('invite_code as ic','ic.id','=','code.code_id')
                ->firstOrFail(['content_id','content_type','content_indexpic','content_title','status','type','order_id','ic.user_id','ic.user_name','ic.avatar','ic.total_num','price','gift_word','code.user_id as user','code.user_name as name','code.user_avatar','start_time','end_time','instruction','code', 'ic.extra_data','code.use_time']);
            $return = $this->getShareResponse($code);
            Cache::forever('code:'.$return['code'],json_encode($return));
            return $this->output($return);
        }

    }

    private function getShareResponse($code)
    {   
       $_ = [
            'content_title' => $code->content_title,
            'content_type'  => $code->content_type,
            'content_id'    => $code->content_id,
            'content_indexpic' => hg_unserialize_image_link($code->content_type=='member_card'?config('define.default_card'):$code->content_indexpic),
            'status'        => intval($code->status),
            'type'          => $code->type,
            'price'         => $code->type=='self'?$code->price:($code->price/intval($code->total_num)),
            'self_receive'  => $this->member['id'] == $code->user ? 1 : 0,
            'receive_user'  =>  $code->user,
            'receive_name'  => $code->name,
            'receive_avatar'=> $code->user_avatar,
            'user_id'       => $code->user_id,
            'user_name'     => $code->user_name,
            'avatar'        => $code->avatar,
            'gift_word'     => $code->gift_word,
            'start_time'    => $code->start_time ? date('Y-m-d',$code->start_time) : '',
            'end_time'      => $code->end_time ? date('Y-m-d',$code->end_time) : '',
            'instruction'   => trim($code->instruction),
            'code'          => $code->code,
            'use_time'      => intval($code->status) == 2 ? date('Y-m-d',$code->use_time): null // 2-已领取
        ];
        if ($code->content_type == 'member_card') {
            $_['membercard_detail'] = null;
            $memberCard = MemberCard::where(['hashid'=>$code->content_id])->firstOrFail();
            if($memberCard) {
                $_['membercard_detail']  = [
                    'style' => $memberCard->style,
                    'option' => $code->extra_data ? unserialize($code->extra_data)['membercard_option']:null,
                    'title' =>  $memberCard->title,
                    'discount' => $memberCard->discount,
                    'use_notice' => $memberCard->use_notice,
                    'discount_explain' => $memberCard->discount_explain,
                    'purchase_notice' => $memberCard->purchase_notice
                ];
            }
        }
        return $_;
    }

    /**
     * 赠送领取
     * @param $code
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function getShare($code)
    {
        $this->memberInstance = $this->getMember();
        $code = Code::where(['code.shop_id'=>$this->shop['id'],'code'=>$code])
            ->leftJoin('invite_code as i','i.id','=','code.code_id')
            ->first(['i.*','code.status','code.id','code.code_id','code.code','code.user_id as member_id']);
        if(!$code){
            $this->error('error_gift_code');
        }
        
        $this->checkContent($code);

        if($code->status == 2){
            if($code->member_id == $this->member['id']) {
                $return  = [
                    'content_id'=>$code->content_id,
                    'content_type'=>$code->content_type,
                    'error'       => 'already_received'
                ];
                if($code->content_type == 'course'){
                    $return['course_type'] = Course::where('hashid',$code->content_id)->value('course_type');
                }
                return $this->output($return);
            }
            $this->error('shared');

        }
        if($code->start_time > time() && $code->type == 'self'){
            $this->error('code_not_start');
        }
        if($code->end_time < time() && $code->type == 'self'){
            $this->error('code_already_end');
        }

        if($code->member_id == $this->member['id']){
            $this->error('self_not_receive');
        }

        $this->checkBuy($code);

        dispatch((new CodePaymentSave($code,$this->member))->onQueue(DEFAULT_QUEUE));
        $this->updateCodeUse($code);
        $return = [
            'content_id'=>$code->content_id,
            'content_type'=>$code->content_type
        ];
        if($code->content_type == 'course'){
            $return['course_type'] = Course::where('hashid',$code->content_id)->value('course_type');
        }
        return $this->output($return);
    }

    private function processMemberRecord($request){
        $card = MemberCard::where(['shop_id'=>$this->shop['id'],'hashid'=>$request->content_id])->first();
        !$card && $this->error('member_card_no_exist');
        $record = CardRecord::where(['member_id'=>$this->member['id'],'card_id'=>$request->content_id])->where('end_time','>',time())->first();
        $record && $this->error('card_already_buy');
    }

    /**
     * 更行邀请码使用信息
     * @param $code
     */
    private function updateCodeUse($code){
        $code->user_id = $this->member['id'];
        $code->user_name = $this->member['nick_name'];
        $code->user_avatar =  $this->member['avatar'];
        $code->status = 2;
        $code->use_time = time();
        $code->saveOrFail();
        Cache::forever('code:use:'.$code->code,$this->member['id']);
        Cache::forget('code:'.$code->code);

        InviteCode::where('id',$code->code_id)->increment('use_num');   //使用说+1
    }

    /**
     * 更新群发赠送码
     */
    private function updateQunFaGiftCode($code, $member_ids)
    {
        $codes = Code::where(['code.shop_id' => $this->shop['id']])
            ->whereIn('code.user_id', $member_ids)
            ->where('code_id', $code->code_id)
            ->get(); // 因为前面只取一条做处理

        foreach ($codes as $code) {
            $code->status = 2;
            $code->use_time = time();
            $code->mobile = $this->memberInstance->mobile;
            $code->saveOrFail();
        }
        
        InviteCode::where('id', $code->code_id)->increment('use_num',$codes->count());   //使用说+1
    }


    /**
     * 我赠送的
     */
    public function myPresentation(Request $request)
    {
        $count = $request->count ?: 10;
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids){
            $sql = InviteCode::whereIn('invite_code.user_id',$member_ids);
        }else{
            $sql = InviteCode::where('invite_code.user_id',$this->member['id']);
        }
        $code = $sql->where('invite_code.shop_id',$this->shop['id'])
            ->leftJoin('code','code.code_id','=','invite_code.id')
            ->where('code','!=','')
            ->orderBy('buy_time','desc')
            ->paginate($count,['content_id','content_title','content_type','content_indexpic','buy_time','status','code']);
        if($code->total() > 0){
            foreach ($code->items() as $v){
                $v->buy_time = $v->buy_time ? date('Y-m-d H:i:s',$v->buy_time) : '';
                $v->content_indexpic = hg_unserialize_image_link($v->content_indexpic);
            }
        }
        return $this->output($this->listToPage($code));
    }

    // 我的权益详情
    public function mygiftDetail(Request $request, $id) {
        $member = $this->getMember();
        $shop = $this->getShop();
        
        $inviteCode = InviteCode::where('id', $id)->firstOrfail();
        $code = Code::where('code_id', $inviteCode->id)->whereIn('user_id', $member->getUnionUids())->firstOrFail();
        $output = [
            "id"=> $inviteCode->id,
            "status"=> $code->status,
            "gift_word"=> $code->gift_word,
            "mobile"=> $code->mobile,
            "created_at"=> $inviteCode->created_at->format('Y-m-d H:i:s'),
            "content_type"=> $inviteCode->content_type,
            "content_id"=> $inviteCode->content_id,
            "content_title" => $inviteCode->content_title,
            "content_indexpic"=> hg_unserialize_image_link($inviteCode->content_indexpic),
            "instruction"=> $inviteCode->instruction,
            "code" => $code->code,
            "type"=> $inviteCode->type,
            "shop"=>[
                "id"=> $shop->hashid,
                "title"=>$shop->title,
                "indexpic"=> $shop->indexpic()
            ]
        ];
        return $this->output($output);        
    }


    private function checkContent($code){
        if ($code->content_type != 'member_card') {
            $content = Content::where(['shop_id' => $this->shop['id'], 'hashid' => $code->content_id])->where('state','!=',2)->first();
            if (!$content) {
                return $this->error('code_content_deleted');
            }
        } else {
            $content = MemberCard::where(['shop_id' => $this->shop['id'], 'hashid' => $code->content_id, 'status'=>1, 'is_del'=>0])->first();
            if(!$content) {
                return $this->error('code_content_deleted');
            }
        }
        return $content;
    }

    private function checkBuy($code){
        $alreadyBuy = false;
        switch ($code->content_type) {
            case 'member_card':
                $record = $this->memberInstance->hasTheMemberCard($code->content_id);
                $record && $alreadyBuy = true;
                break;
            default:
                break;
        }

        if ($alreadyBuy) {
            if ($code->content_type == 'member_card') {
                return $this->error('card_already_buy'); // 会员卡不续期
            }
        }
    }

    // 领取群发赠送
    public function getQunFaGift(Request $request, $id) {
        $this->memberInstance = $this->getMember();
        $member_ids = $this->memberInstance->getUnionUids();
        $shop = $this->getShop();
        $code = Code::where(['code.shop_id' => $this->shop['id']])
            ->whereIn('code.user_id', $member_ids)
            ->where('i.type', 'qunfazengsong')
            ->where('code.code_id', $id)
            ->leftJoin('invite_code as i', 'i.id', '=', 'code.code_id')
            ->firstOrFail(['i.*', 'code.status', 'code.id', 'code.code_id', 'code.code', 'code.user_id as member_id']);
        if($code->status == 2) {
            return $this->error('qunfa_gift_already_received');
        }

        $this->checkContent($code);
        $this->checkBuy($code);

        dispatch((new CodePaymentSave($code, $this->member))->onQueue(DEFAULT_QUEUE));
        $this->updateQunFaGiftCode($code, $member_ids);
        return $this->output(['success'=>1]);
    }
    /***
     * 更改赠送状态
     */
    public function shareCallback(Request $request)
    {
        $code = Code::where('code',$request->code)->firstOrFail();
        $code->status = $code->status == 2 ? 2 : 1;  //赠送成功
        $code->save();
        Cache::forget('code:'.$request->code);
        return $this->output(['success'=>1]);
    }

    /**
     * 赠言编辑接口
     */
    public function giftWord(){
        $this->validateWithAttribute([
            'code'  => 'required|regex:/^\d{4}-\d{4}-\d{4}$/',
            'word'  => 'required',
        ],[
            'code'  => '邀请码',
            'word'  => '赠言',
        ]);
        $inviteCode = Code::where('code',request('code'))->firstOrFail();
        $inviteCode->gift_word = request('word');
        $inviteCode->saveOrFail();
        return $this->output(['success'=>1]);

    }




}