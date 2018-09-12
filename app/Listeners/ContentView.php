<?php

namespace App\Listeners;

use App\Events\ContentViewEvent;
use App\Models\Content;
use App\Models\MemberCard;
use App\Models\Views;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ContentView implements ShouldQueue
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
     * @param  ContentViewEvent  $event
     * @return void
     */
    public function handle(ContentViewEvent $event)
    {
        $this->createViewRecord($event);
    }

    private function createViewRecord($event)
    {
        $content_id = $event->content->hashid;
        $content_type = $event->content->type ?: 'member_card';
        $shop_id = $event->member['shop_id'];
        $member_id = $event->member['member_id'];

        $exists = Views::where(['shop_id' => $shop_id, 'content_id' => $content_id,
            'member_id' => $member_id, 'content_type' => $content_type])->exists();
        if (!$exists) {
            $event->content->increment('unique_member');
        }
        $event->content->increment('view_count');
        if($content_type == 'course' || $content_type == 'column'){
            $content = Content::where(['hashid'=>$content_id, 'shop_id'=>$shop_id, 'type'  => $content_type])->first();
            if($content){
                $content->increment('view_count');
                if (!$exists) {
                    $content->increment('unique_member');
                }
            }
        }

        $view = new Views();
        $param = array_merge([
            'content_id'    => $content_id,
            'content_type'  => $content_type,
            'content_title' => $event->content->title,
            'content_column' => $event->content->column_id ?: '',
        ],$event->member,$event->view);
        $view->setRawAttributes($param);
        $view->saveOrFail();
    }
}
