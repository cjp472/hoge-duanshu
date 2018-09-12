<?php

namespace App\Jobs;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\AppContent;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncBatchContents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $content_type;
    protected $url;

    /**
     * SyncBatchContents constructor.
     * @param $url
     */
    public function __construct($url)
    {
        $this->url = $url;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $client = new Client();
        $client->request('get', $this->url);


    }

}
