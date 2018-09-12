<?php
namespace App\Listeners\Content;

use App\Events\Content\EditEvent;
use App\Models\Column;
use App\Models\Content;

class EditListener
{
    public function handle(EditEvent $event)
    {
        $where = [
            'shop_id'   => $event->shop_id,
            'hashid'    => $event->id,
            'type'      => $event->type,
        ];
        $content = Content::where($where)->first();
        $new = 0;
        if (!$content) {
            //兼容老数据
            $content = new Content();
            $new = 1;
        }
        $content->setRawAttributes(array_merge(
            $this->setBase($event),
            $event->info
        ));
        if ($new) {
            $content->column_id = $event->type == 'course' ? 0 : Column::where('hashid', $event->id)->value('id');
            $content->shop_id = $event->shop_id;
            $content->hashid = $event->id;
            $content->type = $event->type;
            $content->up_time = time();
            $content->create_time = time();
            $content->create_user = $event->user['id'];
        }
        $content->save();
    }

    private function setBase($event)
    {
        return [
            'update_time'  => $event->time,
            'update_user' => $event->user['id'],
        ];
    }
}