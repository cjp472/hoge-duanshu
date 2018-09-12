<?php
namespace App\Listeners\Content;

use App\Events\Content\CreateEvent;
use App\Models\Content;

class CreateListener
{
    public function handle( CreateEvent $event)
    {
        Content::insert(array_merge(
            $this->setBase($event),
            $event->info
        ));
    }

    private function setBase($event)
    {
        return [
            'hashid'    => $event->id,
            'shop_id'  => $event->shop_id,
            'create_time'  => $event->time,
            'update_time'  => $event->time,
            'up_time'  => $event->time,
            'create_user' => $event->user['id'],
            'update_user' => $event->user['id'],
            'play_count' => 0,
            'end_play_count' => 0,
            'share_count'   => 0,
            'type' => $event->type,
            'comment_count' => 0,
            'view_count' => 0,
            'subscribe' => 0,
            'display' => 1,
            'is_lock' => 0,
        ];
    }
}