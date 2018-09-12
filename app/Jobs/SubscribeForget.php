<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Models\Praise;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SubscribeForget implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $content;
    protected $member_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($content,$member_id)
    {
        $this->content = $content;
        $this->member_id = $member_id;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
       Redis::srem('subscribe:'.$this->content->shop_id.':'.$this->member_id,$this->content->hashid);
    }
}
