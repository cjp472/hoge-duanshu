<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundOrder extends Model
{
    protected $table = 'refund_order';

    /**
     * 关联订单数据
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order(){
        return $this->hasOne('App\Models\Order','order_id','order_id');
    }

}
