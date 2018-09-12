<?php
namespace App\Listeners\Log;


use App\Models\Logs;
use App\Models\Log\Logs as LogTemp;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogSuccessfulLogin implements ShouldQueue
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
     * @param  Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        $log = new Logs();
        $log->title = '【登录】 '.$event->user->name;
        $log->type = 'login';
        $log->user_id = $event->user->id;
        $log->user_name = $event->user->name;
        $log->time = time();
        $log->save();

        $lt = new LogTemp();
        $lt->title = '【登录】 '.$event->user->name;
        $lt->type = 'login';
        $lt->user_id = $event->user->id;
        $lt->user_name = $event->user->name;
        $lt->time = time();
        $lt->save();
    }
}