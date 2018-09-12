<?php
namespace App\Events;

class PromoterRecordEvent
{
    public function __construct($promoterId,$order)
    {
        $this->promoterId = $promoterId;
        $this->order = $order;
    }
}