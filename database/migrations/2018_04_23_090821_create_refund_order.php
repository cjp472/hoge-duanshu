<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRefundOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('refund_order', function (Blueprint $table) {
            $table->integer('id',1);
            $table->string('order_no',64)->default('')->comment('订单中心订单号');
            $table->string('order_id',22)->default('')->comment('短书内部订单号');
            $table->string('refund_no',64)->default('')->comment('退款单号');
            $table->tinyInteger('refund_status')->default(1)->comment('退款状态，1-退款中，2-退款成功，3-退款失败');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('refund_order');
    }
}
