<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRefundIdToRefundOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('refund_order', function (Blueprint $table) {
            $table->string('shop_id',18)->default('')->comment('店铺id');
            $table->string('wechat_refund_id',64)->default('')->comment('微信退款单号');
            $table->string('order_center_refund_id',64)->default('')->comment('订单中心退款单号');
            $table->text('extra')->default('')->comment('微信退款回调数据冗余');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
