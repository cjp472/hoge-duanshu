<?php

namespace App\Events\AppEvent;

use App\Models\Content;

class AppContentEvent
{
    /**
     * AppContentEvent constructor.
     * @param Content $content
     */
    public function __construct(Content $content)
    {
        $this->data = $content;
    }


}
