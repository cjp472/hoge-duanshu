<?php

namespace App\Events;

use App\Models\Content;
use Illuminate\Http\Request;

class ContentViewEvent
{
    /**
     * ContentViewEvent constructor.
     * @param $content
     * @param array $member
     */
    public function __construct($content,$member = [])
    {
        $this->content = $content;
        $this->member = [
            'member_id' => $member['id'],
            'source'    => request('source') ?: $member['source'],
            'shop_id'   => request('shop_id'),
        ];
        $this->view = [
            'view_time'     => time(),
            'user_agent'    => isSet($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip'            => hg_getip()
        ];
    }
}
