<?php

namespace App\Listeners;

use App\Events\InteractNotifyEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class InteractNotify implements ShouldQueue
{
    use InteractsWithQueue;
    /**
     * 队列名称
     * @var string
     */
    public $queue = DEFAULT_QUEUE;
    /**
     * InteractNotify constructor.
     * @param InteractNotifyEvent $interactNotifyEvent
     */
    public function handle(InteractNotifyEvent $interactNotifyEvent)
    {
        if($interactNotifyEvent->member_id != $interactNotifyEvent->interact_id){
            $interactNotify = new \App\Models\InteractNotify();
            $interactNotify->setRawAttributes(get_object_vars($interactNotifyEvent));
            $interactNotify->save();
            Cache::increment('interact:notify:number:'.$interactNotifyEvent->member_id);
        }
    }
}
