<?php

namespace App\Events\AppEvent;

use App\Models\Column;

class AppColumnEvent
{
    /**
     * AppColumnEvent constructor.
     * @param Column $column
     */
    public function __construct(Column $column)
    {
        $this->data = $column;
    }


}
