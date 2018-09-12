<?php

namespace App\Listeners;

use App\Events\AudioTranscodeEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\OperationEvent;
use GuzzleHttp\Client;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use qcloudcos\Cosapi;

class AudioTranscode implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = QUEUE_NAME;

    /**
     * Handle the event.
     *
     * @param  AudioTranscodeEvent  $event
     * @return void
     */
    public function handle(AudioTranscodeEvent $event)
    {
        if(!file_exists($event->target_file))
        {
            $command = '/usr/local/ffmpeg/bin/ffmpeg -i '.$event->file.' -b:a 192k -acodec mp3 -ar 44100 -ac 2 '.$event->target_file .' 2>/dev/null';
            try{
                exec($command);
            }catch (\Exception $exception){
                event(new ErrorHandle($exception,'ffmpeg'));
            }
        }
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$event->target_file,$event->dstPath);
        event(new CurlLogsEvent(json_encode($data),new Client(),'http://region.file.myqcloud.com/files/v2/'));

//        !$data['code'] && @unlink($event->target_file);
//        file_exists($event->file) && @unlink($event->file);


    }
}
