<?php

namespace App\Listeners\Log;

use App\Events\CurlLogsEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\CurlLogs;
use App\Models\Log\CurlLogs as LogsTemp;


class LogCurl implements ShouldQueue
{
    use InteractsWithQueue;
    /**
     * 队列名称
     * @var string
     */
    public $queue = 'log';
    /**
     * Handle the event.
     *
     * @param  CurlLogsEvent  $event
     * @return void
     */
    public function handle(CurlLogsEvent $event)
    {
        $log = new CurlLogs();
        $log->setRawAttributes($event->param);
        $log->save();


        $lt = new LogsTemp($event->param);
        $lt->save();
    }
}
