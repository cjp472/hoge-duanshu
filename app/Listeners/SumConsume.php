<?php
namespace App\Listeners;

use App\Events\PayEvent;
use App\Models\Member;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SumConsume implements ShouldQueue
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
     * @param  ContentViewEvent  $event
     * @return void
     */
    public function handle(PayEvent $event)
    {
        $price = $event->order->price;
        $member = Member::where([
            'uid'    => $event->order->user_id,
            'shop_id'=> $event->order->shop_id
        ])->firstOrFail();
        $member->increment('amount',$price);
    }

    public function failed(PayEvent $event)
    {
        file_put_contents(storage_path('/logs/faileQueue.txt'),date('Y.m.d H:i:s').'add-amount:'.$event->order->user_id.':'.$event->order->price."\n",FILE_APPEND);
    }
}
