<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCronStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cron_statistics', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('create_time')->comment('创建时间');
            $table->integer('member')->comment('新增会员数');
            $table->integer('paid_member')->comment('新增付费会员数');
            $table->integer('user')->comment('新增用户数');
            $table->integer('paid_user')->comment('新增付费用户数');
            $table->integer('active_user')->comment('新增活跃用户数');
            $table->float('yesterday_income')->comment('昨日收入');
            $table->integer('click_num')->comment('阅读量');
            $table->integer('order_num')->comment('订单数');
            $table->char('shop_id',12)->comment('店铺id');
            $table->integer('year')->comment('年份');
            $table->integer('month')->comment('月份');
            $table->integer('day')->comment('天');

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
        Schema::dropIfExists('cron_statistics');
    }
}
