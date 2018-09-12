<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFailContentSyn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fail_content_syn', function (Blueprint $table) {
            $table->increments('id');
            $table->string('route',200)->comment('操作路由');
            $table->text('input_data')->nullable()->comment('输入数据');
            $table->char('shop_id')->comment('店铺id');
            $table->integer('create_time')->comment('时间');
            $table->tinyInteger('is_sync')->comment('是否同步');

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
        Schema::drop('fail_content_syn');
    }
}
