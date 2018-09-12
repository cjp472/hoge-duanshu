<?php

namespace App\Jobs;


use App\Events\JoinCommunityEvent;
use App\Events\SubscribeEvent;
use App\Models\CardRecord;
use App\Models\Column;
use App\Models\Community;
use App\Models\CommunityUser;
use App\Models\MemberCard;
use App\Models\Member;
use App\Models\Content;
use App\Models\Course;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CodePaymentSave
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $coder;
    protected $member;

    /**
     * CodePaymentSave constructor.
     * @param $code
     * @param $member
     */
    public function __construct($code,$member)
    {
        $this->coder = [
            'content_id'        => $code->content_id,
            'content_type'      => $code->content_type,
            'content_title'     => $code->content_title,
            'content_indexpic'  => $code->content_indexpic,
            'order_id'          => $code->order_id,
            'buy_time'          => $code->buy_time,
            'price'             => $code->price,
            'shop_id'           => $code->shop_id,
            'type'              => $code->type,
            'user_id'           => $code->user_id,
            'user_name'         => $code->user_name,
        ];
        $this->code = $code;
        $this->member = $member;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $member = Member::where('uid',$this->member['id'])->first();
        $paymentType = $this->coder['type'] == 'share' ? 2 : 3;
        switch ($this->coder['content_type']) {
            case 'article':
            case 'video':
            case 'audio':
            case 'live':
                $content = Content::where('hashid',$this->coder['content_id'])->first();
                $payment = Payment::freeSubscribeContent($member,$this->coder['content_type'],$content,$paymentType,0);
                break;
            case 'column':
                $content = Column::where('hashid', $this->coder['content_id'])->first();
                $payment = Payment::freeSubscribeContent($member, 'column', $content, $paymentType, 0);
                break;
            case 'course':
                $content = Course::where('hashid', $this->coder['content_id'])->first();
                $payment = Payment::freeSubscribeContent($member, 'course', $content, $paymentType, 0);
                break;
            case 'member_card':
                $content = MemberCard::where('hashid', $this->coder['content_id'])->first();
                $content->setIndexPic();
                $content->indexpic = serialize($content->indexpic);
                $payment = Payment::freeSubscribeContent($member, 'member_card', $content, $paymentType, 0);
                break;
            case 'community':
                $content = Community::where('hashid', $this->coder['content_id'])->first();
                $payment = Payment::freeSubscribeContent($member, 'community', $content, $paymentType, 0);
                break;
            default:
                return;
                break;
        }
        
        if($this->coder['content_type'] == 'column') {
            Cache::forever('payment:' . $this->coder['shop_id'] . ':' . $this->member['id'] . ':' . $this->coder['content_id'] . ':' . $this->coder['content_type'],$this->coder['order_id'] ? : -1);
            $this->saveContentId($this->coder,$this->member['id']);
        }elseif($this->coder['content_type'] == 'course') {
            Cache::forever('payment:' . $this->coder['shop_id'] . ':' . $this->member['id'] . ':' . $this->coder['content_id'] . ':' . $this->coder['content_type'],$this->coder['order_id'] ? : -1);
        }elseif($this->coder['content_type'] == 'member_card'){
            $this->createCardRecord($payment, $this->code);
            Cache::forever('payment:' . $this->coder['shop_id'] . ':' . $this->member['id'] . ':' . $this->coder['content_id'] . ':' . $this->coder['content_type'],$this->coder['order_id'] ? : -1);
        }elseif($this->coder['content_type'] == 'community'){
            $source = $this->coder['type'] == 'share'? 'purchase_gift':'self_gift';
            $this->createCommunityUser($payment, $source);
            Cache::forever('payment:' . $this->coder['shop_id'] . ':' . $this->member['id'] . ':' . $this->coder['content_id'] . ':' . $this->coder['content_type'],$this->coder['order_id'] ? : -1);
        }else{
            Cache::forever('payment:' . $this->coder['shop_id'] . ':' . $this->member['id'] . ':' . $this->coder['content_id'],$this->coder['order_id'] ? : -1);
        }
        event(new SubscribeEvent($this->coder['content_id'], $this->coder['content_type'], $this->coder['shop_id'], $this->member['id'], $paymentType));
    }

    /**
     * 会员加入到社群
     * @param $info
     */
    private function createCommunityUser($info, $source){
        $community = Community::where(['hashid'=>$info->content_id])->first();
        //判断该社群存在
        if($community) {
            $params =[
                'shop_id' => $info->shop_id,
                'community_id' => $info->content_id,
                'member_id' => $info->user_id,
                'member_name' => $info->nickname,
                'source' => $source,
            ];
            event(new JoinCommunityEvent($params));
        }
    }

    private function saveContentId($data,$user_id){
        $content_id = Column::where(['column.hashid'=>$data['content_id'],'column.shop_id'=>$data['shop_id']])->leftJoin('content','content.column_id','=','column.id')->where('content.payment_type',1)->pluck('content.hashid')->toArray();
        if($content_id){
            Redis::sadd('subscribe:h5:'.$data['shop_id'].':'.$user_id,$content_id);
        }
    }

    private function createCardRecord($pay, $code){
        $data = Payment::where('id',$pay->id)->first();
        $card = MemberCard::where(['shop_id'=>$data->shop_id,'hashid'=>$data->content_id])->first();
        $membercard_option = unserialize($code->extra_data)['membercard_option'];
        $expire_time = MemberCard::getOptionValueExpire($membercard_option['value']);
        $time = date_create(hg_format_date($data->order_time));
        date_add($time,date_interval_create_from_date_string($expire_time));//计算到期时间
        $param = [
            'card_id'    => $card->hashid,
            'title'      => $card->title,
            'member_id'  => $data->user_id,
            'nick_name'  => $data->nickname,
            'source'     => $this->member['source']?:$data->source,
            'shop_id'    => $data->shop_id,
            'start_time' => $data->order_time,
            'end_time'   => strtotime(date_format($time,'Y-m-d H:i:s')),
            'order_id'   => $data->order_id,
            'card_type'  => $card->card_type,
            'price'      => $card->price,
            'discount'   => $card->discount,
            'order_time' => $data->order_time,
            'option'     => serialize($membercard_option)
        ];
        $card_record = new CardRecord();
        $card_record->setRawAttributes($param);
        $card_record->saveOrFail();

        $count = CardRecord::where([
            'shop_id'=>$data->shop_id,
            'card_id'=>$data->content_id
            ])->count();
        MemberCard::where([
            'shop_id'=>$data->shop_id,
            'hashid'=>$data->content_id
        ])->update(['subscribe'=>$count]);
    }
}
