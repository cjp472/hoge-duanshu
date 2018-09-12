<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotify extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notify', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->integer('sender')->comment('发送人Id');
            $table->string('sender_name',32)->nullable()->comment('发送人名字');
            $table->char('recipients',32)->nullable()->comment('接收人');
            $table->string('recipients_name',32)->nullable()->comment('接收人名字');
            $table->text('content')->comment('信息');
            $table->integer('send_time')->comment('发送时间');
            $table->tinyInteger('status')->default(1)->comment('发送状态');
            $table->tinyInteger('type')->comment('发送方式 0私人 1群发');
            $table->string('link_info',256)->nullable()->comment('跳转链接');

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
        Schema::drop('notify');
    }
}
