<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContentStatistics extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('content_statistics', function (Blueprint $table) {
            $table->increments('id');
            $table->char('type',16)->comment('类型');
            $table->integer('create_time')->comment('创建时间');
            $table->float('yesterday_income')->default(0.00)->comment('收入');
            $table->integer('click_num')->default(0)->comment('阅读量');
            $table->integer('order_num')->default(0)->comment('订单数');
            $table->char('shop_id',12)->comment('店铺id');
            $table->integer('year')->comment('年份');
            $table->integer('month')->comment('月份');
            $table->integer('day')->comment('天');
            $table->integer('week')->comment('周');
            $table->timestampsTz();

            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('content_statistics');
    }
}
