<?php
namespace App\Listeners\Log;

use App\Models\Logs;
use App\Models\Log\Logs as LogTemp;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogSuccessfulLogout implements ShouldQueue
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
     * @param  Logout  $event
     * @return void
     */
    public function handle(Logout $event)
    {
        if($event->user){
            $log = new Logs();
            $log->title = '【登出】 '.$event->user->name;
            $log->type = 'logout';
            $log->user_id = $event->user->id;
            $log->user_name = $event->user->name;
            $log->time = time();
            $log->save();

            $lt = new LogTemp();
            $lt->title = '【登出】 '.$event->user->name;
            $lt->type = 'logout';
            $lt->user_id = $event->user->id;
            $lt->user_name = $event->user->name;
            $lt->time = time();
            $lt->save();
        }
    }
}