<?php

namespace App\Events;


class AdmireEvent
{

    /**
     * 赞赏成功事件
     *
     * @return void
     */
    public function __construct($admireOrder)
    {
        $this->admireOrder = $admireOrder;
    }
}
