<?php
namespace App\Events\Content;


class StaticsEvent
{
    public function __construct($shop_id,$beginYesterday,$endYesterday)
    {
        $this->shop_id = $shop_id;
        $this->begin = $beginYesterday;
        $this->end = $endYesterday;
    }
}