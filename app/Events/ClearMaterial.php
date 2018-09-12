<?php
namespace App\Events;


class ClearMaterial
{
    public function __construct($shop_id)
    {
        $this->shop_id = $shop_id;
        
    }
}