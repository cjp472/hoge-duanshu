<?php
namespace App\Listeners\Log;

use App\Events\ErrorHandle;
use App\Models\ErrorLogs;
use App\Models\Log\ErrorLogs as LogsTemp;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogError implements ShouldQueue
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
     * @param  ErrorHandle  $event
     * @return void
     */
    public function handle(ErrorHandle $event)
    {
        $log = new ErrorLogs();
        $log->setRawAttributes($event->param);
        $log->save();

        $lt = new LogsTemp($event->param);
        $lt->save();
        $this->sendDingMsg($event->param['route'],$event->error,$log->id);
    }

    private function sendDingMsg($route,$msg,$id)
    {
        $config = config('define.dingding.notice');
        $msgs = $this->setContent($route,$msg,$config['link'].$id);
        $webhook = $config['api'].'?access_token='.$config['token'];
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'  => json_encode([
                'msgtype'   => 'markdown',
                'markdown'  => [
                    'text'   => $msgs,
                    'title'   => '短书接口出错啦!'.$route,
                    'messageUrl'    => $config['link'].$id,
                ]
            ]),
        ]);
        $res = $client->request('POST',$webhook);
        $res->getBody()->getContents();
    }

    private function setContent($route,$error = [],$link = '')
    {
        return "#### **短书接口出错啦!**[".$route ."]\n".
            " **[".$error['class']."]** *".$error['message']."*\n\n".
            "> ".$error['file'].'(line:'.$error['line'].')'."\n\n".
            "[查看详情](".$link.") "."\n";
    }
}