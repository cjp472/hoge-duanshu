<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\PromotionRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CheckOrderPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(!$this->order->pay_status){
            $this->order->pay_status = -1;
            $this->order->save();
            $promotion_record = PromotionRecord::where(['order_id'=>$this->order->order_id])->first();
            if($promotion_record){
                //推广员记录关闭
                $promotion_record->state = 2;
                $promotion_record->save();
            }

        }
    }
}
