<?php

namespace App\Listeners\Log;

use App\Events\AdminLogsEvent;
use App\Models\Log\AdminLogs as LogsTemp;;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Manage\AdminLogs as Log;

class AdminLogs implements ShouldQueue
{
    use InteractsWithQueue;
    public $queue = 'log';
    /**
     * Handle the event.
     *
     * @param  AdminLogsEvent  $event
     * @return void
     */
    public function handle(AdminLogsEvent $event)
    {
        $data = $event->param;
        Log::insert($data);
        $lt = new LogsTemp($data);
        $lt->save();
    }
}
