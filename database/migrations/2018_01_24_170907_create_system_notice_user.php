<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSystemNoticeUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_notice_user', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id')->comment('店铺id');
            $table->integer('notice_id')->comment('通知id');
            $table->tinyInteger('is_read')->default(0)->comment('是否已读');

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
        Schema::drop('system_notice_user');
    }
}
