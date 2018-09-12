<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/9/27
 * Time: 18:02
 */

namespace App\Jobs;


use App\Events\AppEvent\AppContentEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AppContentSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($content)
    {
        $this->data = $content;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        event(new AppContentEvent($this->data));
    }

}