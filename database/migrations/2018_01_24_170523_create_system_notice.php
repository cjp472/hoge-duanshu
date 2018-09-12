<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSystemNotice extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_notice', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id')->comment('店铺id');
            $table->string('title',32)->comment('通知标题');
            $table->text('content')->comment('信息');
            $table->integer('user_id')->nullable()->comment('用户Id');
            $table->string('user_name',32)->nullable()->comment('用户名字');
            $table->integer('send_time')->comment('发送时间');
            $table->tinyInteger('send_type')->comment('发送方式 0-单个店铺 1-所有店铺');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除');

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
        Schema::drop('system_notice');
    }
}
