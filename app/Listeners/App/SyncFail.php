<?php

namespace App\Listeners\App;

use App\Events\AppEvent\AppSyncFailEvent;
use App\Models\FailContentSyn;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncFail
{

    /**
     * Handle the event.
     *
     * @param  AppSyncFailEvent  $event
     * @return void
     */
    public function handle(AppSyncFailEvent $event)
    {
        FailContentSyn::insert(['route'=>$event->route,'input_data'=>$event->input_data,'create_time'=>hg_format_date(),'shop_id'=>$event->shop_id]);
    }
}
