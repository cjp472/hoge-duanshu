<?php
namespace App\Listeners\Log;

use App\Events\H5LogsEvent;
use App\Models\H5Logs;
use App\Models\Log\H5Logs as LogsTemp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class H5LogOperationed implements ShouldQueue
{
    use InteractsWithQueue;
    /**
     * 队列名称
     * @var string
     */
    public $queue = 'log';

    /**
     * @param H5LogsEvent $event
     */
    public function handle(H5LogsEvent $event)
    {
        $log = new H5Logs();
        $log->setRawAttributes($event->param);
        $log->save();

        $lt = new LogsTemp($event->param);
        $lt->save();
    }
}