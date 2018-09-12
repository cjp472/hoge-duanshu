<?php

namespace App\Jobs;

use App\Models\PublicArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CachePublicArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $article;
    protected $shop_id;

    /**
     * CachePublicArticle constructor.
     * @param $data
     */
    public function __construct($data,$shop_id)
    {
        $this->article = $data;
        $this->shop_id = $shop_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        PublicArticle::where(['media_id' => $this->article['media_id']])->delete();
        $save_info = [
            'shop_id' => $this->shop_id,
            'media_id' => $this->article['media_id'],
            'content' => serialize($this->article),
        ];
        PublicArticle::insert($save_info);

    }
}
