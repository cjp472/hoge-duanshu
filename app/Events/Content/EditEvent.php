<?php

namespace App\Events\Content;
class EditEvent
{
    public function __construct($hashid,$type = '',$info = [],$shop_id = '',$user = '')
    {
        $this->info = $info;
        $this->time = time();
        $this->shop_id = $shop_id;
        $this->user = $user;
        $this->id = $hashid;
        $this->type = $type;
    }
}