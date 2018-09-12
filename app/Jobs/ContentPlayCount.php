<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2017/6/23
 * Time: 上午10:13
 */


namespace App\Jobs;


use App\Models\Content;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ContentPlayCount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $content;
    protected $shop_id;
    protected $content_id;
    protected $sign;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($shop_id,$sign,$content_id)
    {
        $this->shop_id = $shop_id;
        $this->sign = $sign;
        $this->content_id = $content_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $content = Content::where(['hashid'=>$this->content_id,'shop_id'=>$this->shop_id])->first();
        if($content) {
            $this->sign == 'play' && $content->increment('play_count', 1);
            $this->sign == 'end_play' && $content->increment('end_play_count', 1);
            $this->sign == 'share' && $content->increment('share_count', 1);
        }
    }
}
