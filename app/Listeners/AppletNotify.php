<?php
/**
 * Created by PhpStorm.
 * User: a123456
 * Date: 2017/6/1
 * Time: 上午10:10
 */

namespace App\Listeners;


use App\Events\AppletNoticeEvent;
use App\Models\Manage\AppletNotice;
use Illuminate\Queue\InteractsWithQueue;


class AppletNotify
{
    use InteractsWithQueue;


    public function handle(AppletNoticeEvent $appletNoticeEvent){
        $system = new AppletNotice();
        $system->setRawAttributes($appletNoticeEvent->params);
        $system->save();
    }

}