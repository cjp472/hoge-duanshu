<?php
/**
 * Created by PhpStorm.
 * User: Janice
 * Date: 2018/6/25
 * Time: 10:33
 */

namespace App\Events;


class SettlementEvent
{
    /**
     * SettlementEvent constructor.
     * @param $shop_id 店铺id
     * @param $time 计算时间
     */
    public function __construct($shop_id,$time)
    {
        $this->shop_id = $shop_id;
        $this->time = $time;
    }
}