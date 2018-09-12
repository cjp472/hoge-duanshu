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

class CommentPraise implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $comment_id;
    protected $member_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($comment_id,$member_id)
    {
        $this->comment_id = $comment_id;
        $this->member_id = $member_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $comment = Comment::findOrFail($this->comment_id);
        $comment->praise =  Cache::get('comment:praise:sum:'.$this->comment_id);
        $comment->save();

        $praise = Praise::where(['comment_id' => $this->comment_id,'member_id' => $this->member_id])->first();
        if($praise){
            $praise->praise_num =  Cache::get('comment:praise:status:'.$this->comment_id.':'.$this->member_id);
            $praise->save();
        }else{
            Praise::insert(['comment_id' => $this->comment_id,'member_id' => $this->member_id,'praise_time' => time()]);
        }
    }
}
