<?php
namespace App\Events;

class PushTemplateEvent
{
    public function __construct($content,$access_token='')
    {
        $this->content = $content;
        $this->access_token = $access_token;
    }
}