<?php
namespace App\Listeners;


use App\Events\CreateCardRecord;
use App\Models\CardRecord;
use App\Models\MemberCard;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class AddCardRecord
{
    use InteractsWithQueue;

    /**
     * 队列名称
     * @var string
     */
    public $queue = DEFAULT_QUEUE;

    /**
     * Handle the event.
     *
     * @param  CreateCardRecord  $event
     * @return void
     */
    public function handle(CreateCardRecord $event)
    {
        $data = $event->data;
        $card = MemberCard::where(['shop_id'=>$data->shop_id,'hashid'=>$data->content_id])->first();
        $option = $data->getExtraData()['membercard_option'];
        $optionValue = $option['value'];
        hg_fpc('选择规格'.$optionValue);
        $expire = $card->getOptionValueExpire($optionValue);
        $time = date_create(hg_format_date($data->pay_time));
        // intval($expire) < 0 ? $expire = '600 seconds' : $expire = $expire.' months';   //-1为测试数据
        hg_fpc('有效期'.$expire);
        date_add($time,date_interval_create_from_date_string($expire));//计算到期时间
        $param = [
            'card_id'    => $card->hashid,
            'title'      => $card->title,
            'member_id'  => $data->user_id,
            'nick_name'  => $data->nickname,
            'source'     => $data->source,
            'shop_id'    => $data->shop_id,
            'start_time' => $data->pay_time,
            'end_time'   => strtotime(date_format($time,'Y-m-d H:i:s')),
            'order_id'   => $data->order_id,
            'card_type'  => $card->card_type,
            'price'      => $card->price,
            'discount'   => $card->discount,
            'order_time' => $data->pay_time,
            'option'     => serialize($option)
        ];
        $card_record = new CardRecord();
        $card_record->setRawAttributes($param);
        $card_record->save();

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
