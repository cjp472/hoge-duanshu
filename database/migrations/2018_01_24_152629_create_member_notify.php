<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMemberNotify extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('member_notify', function (Blueprint $table) {
            $table->increments('id');
            $table->char('member_id',32)->comment('会员id');
            $table->integer('notify_id')->comment('消息通知id');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除');
            $table->tinyInteger('is_display')->default(1)->comment('是否显示');
            $table->tinyInteger('is_read')->default(0)->comment('是否已读');

            $table->index('member_id');
            $table->index(['member_id','notify_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('member_notify');
    }
}
