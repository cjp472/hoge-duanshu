<?php
namespace App\Listeners\Log;

use App\Models\Logs;
use App\Models\Log\Logs as LogTemp;
use App\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogRegisteredUser implements ShouldQueue
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
     * @param  Registered  $event
     * @return void
     */
    public function handle(Registered $event)
    {
        $log = new Logs();
        $log->title = '【注册】 '.$event->user->name;
        $log->type = 'register';
        $log->user_id = $event->user->id;
        $log->user_name = $event->user->name;
        $log->time = time();
        $log->save();


        $lt = new LogTemp();
        $lt->title = '【注册】 '.$event->user->name;
        $lt->type = 'register';
        $lt->user_id = $event->user->id;
        $lt->user_name = $event->user->name;
        $lt->time = time();
        $lt->save();
    }
}