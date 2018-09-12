<?php
namespace App\Listeners\Log;

use App\Events\OperationEvent;
use App\Models\Logs;
use App\Models\Log\Logs as LogsTemp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogOperationed implements ShouldQueue
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
     * @param  OperationEvent  $event
     * @return void
     */
    public function handle(OperationEvent $event)
    {
        $log = new Logs();
        $log->setRawAttributes($event->param);
        $log->save();

        $lt = new LogsTemp($event->param);
        $lt->save();
    }
}