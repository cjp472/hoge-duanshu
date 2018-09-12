<?php
/**
 * Created by PhpStorm.
 * User: a123456
 * Date: 2017/6/1
 * Time: 上午10:10
 */

namespace App\Listeners;


use App\Events\SystemEvent;
use App\Models\SystemNotice;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SystemNotify
{
    use InteractsWithQueue;


    public function handle(SystemEvent $systemEvent){
        $system = new SystemNotice();
        $system->setRawAttributes($systemEvent->params);
        $system->save();
    }

}