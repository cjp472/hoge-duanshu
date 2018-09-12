<?php
namespace App\Events\Content;

class CreateEvent
{
    public function __construct($hashid,$info = [],$type = '',$shop_id = '',$user = '')
    {
        $this->info = $info;
        $this->time = time();
        $this->shop_id = $shop_id;
        $this->user = $user;
        $this->id = $hashid;
        $this->type = $type;
    }
}