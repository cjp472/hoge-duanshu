<?php

namespace App\Jobs;

use App\Models\PromotionRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncPromotionRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $param;


    /**
     * SyncPromotionRecord constructor.
     * @param $promotion_record
     */
    public function __construct($promotion_record)
    {
        $this->param = $promotion_record;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $promotion_record = $this->param;
        if(PromotionRecord::where(['order_id'=>$promotion_record->order_id,'promotion_type'=>'visit'])->value('id')){
            return false;
        }
        $data = [
            'order_id' => $promotion_record->order_id,
            'shop_id'  => $promotion_record->shop_id,
            'promotion_id' => $promotion_record->visit_id,
            'visit_id' => null,
            'buy_id'   => $promotion_record->buy_id ? : '',
            'content_id' => $promotion_record->content_id ? :'',
            'content_type' => $promotion_record->content_type?:'',
            'content_title' => $promotion_record->content_title?:'',
            'deal_money' => $promotion_record->deal_money?:0,
            'money_percent' => $promotion_record->visit_percent?:0,
            'visit_percent' => 0,
            'state' => 0,
            'create_time' => $promotion_record->create_time,
            'promotion_type'    => 'visit'
        ];
        $promotion = new PromotionRecord();
        $promotion->setRawAttributes($data);
        $promotion->save();

    }
}
