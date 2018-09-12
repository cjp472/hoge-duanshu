<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserButtonClick extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_button_click', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('type',32)->comment('按钮类型，advanced-高级版升级、续费，fullplat-全平台申请');
            $table->integer('click_time');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('user_button_click');
    }
}
