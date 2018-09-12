<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCardRecord extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('card_record', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('card_id',12)->comment('会员卡id');
            $table->string('title',32)->comment('会员卡标题');
            $table->char('member_id',32)->comment('会员id');
            $table->string('nickname',64)->comment('昵称');
            $table->string('source',32);
            $table->integer('start_time')->comment('起始时间');
            $table->integer('end_time')->comment('结束时间');
            $table->string('order_id')->comment('订单id');
            $table->tinyInteger('card_type')->comment('会员卡类型1-全场通用 2-指定商品');
            $table->decimal('price',8,2)->comment('会员卡价格');
            $table->string('discount',12)->comment('折扣 -1-免费 1-1折 2-2折');
            $table->integer('order_time')->comment('订单时间');
            $table->index('shop_id');
            $table->index(['shop_id','card_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('card_record');
    }
}
