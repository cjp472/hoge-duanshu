<?php
namespace App\Listeners\Content;

use App\Events\Content\DeleteEvent;
use App\Models\Content;

class DeleteListener
{

    public function handle(DeleteEvent $event)
    {
        $where = [
            'shop_id'   => $event->shop_id,
            'hashid'    => $event->id,
            'type'      => $event->type,
        ];
        Content::where($where)->delete();
    }
}