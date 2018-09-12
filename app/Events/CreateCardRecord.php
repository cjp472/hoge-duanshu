<?php
/**
 * 支付完成后添加会员卡购买记录
 */

namespace App\Events;


class CreateCardRecord
{
    public function __construct($data)
    {
        $this->data = $data;
    }
}