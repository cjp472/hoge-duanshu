<?php

namespace App\Events\Content;
class DeleteEvent
{
    public function __construct($hashid,$type,$shop_id)
    {
        $this->id = $hashid;
        $this->type = $type;
        $this->shop_id = $shop_id;
    }
}