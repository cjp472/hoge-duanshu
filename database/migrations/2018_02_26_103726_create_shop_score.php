<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopScore extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_score', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('order_id',32)->comment('订单号');
            $table->string('order_type',16)->comment('订单类型');
            $table->float('order_price')->comment('订单价格');
            $table->integer('order_time')->comment('订单时间');
            $table->float('score')->comment('短书币');
            $table->string('project',64)->comment('购买项');
            $table->integer('order_status')->comment('结算状态（1正常-1欠费）');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('shop_score');
    }
}
