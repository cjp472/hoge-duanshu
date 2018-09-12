<?php

namespace App\Listeners;

use App\Events\CommentEvent;
use App\Models\CommunityNote;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Content;
use App\Models\Course;
use App\Models\SystemNotice;

class CommentListener implements ShouldQueue
{
    use InteractsWithQueue;
    /**
     * 队列名称
     * @var string
     */
    public $queue = DEFAULT_QUEUE;
    /**
     * Handle the event.
     *
     * @param  CommentEvent  $event
     * @return void
     */
    public function handle(CommentEvent $event)
    {
        if($event->id)
        {
            switch ($event->type){
                case 'note':
                    CommunityNote::where([
                        'hashid'    => $event->id,
                        'shop_id'   => $event->shop_id
                    ])->increment('comment_num');
                    break;
                default:
                    Content::where([
                        'hashid'    => $event->id,
                        'type'      => $event->type,
                        'shop_id'   => $event->shop_id
                    ])->increment('comment_count');
                    break;
            }

            if ($event->type == 'course')  {
                $course = Course::where('hashid', $event->id)->first();
                if($course){
                    SystemNotice::sendShopSystemNotice(
                        $event->shop_id,
                        'notice.title.course_commnet_audit',
                        'notice.content.course_commnet_audit',
                        ['course_title' => $course->title,'course_id'=>$course->hashid]
                    );
                }
            }

        }
    }

    public function failed(CommentEvent $event)
    {
        file_put_contents(storage_path('/logs/faileQueue.txt'),date('Y.m.d H:i:s').'content-id:'.$event->id."\n");
    }
}
