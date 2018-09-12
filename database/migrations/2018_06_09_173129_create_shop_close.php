<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopClose extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_close', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('method',18)->comment('处理方式');
            $table->integer('event_time')->comment('事件时间');
            $table->integer('process_time')->comment('处理时间');
            $table->string('reason',32)->comment('处理原因');
            $table->tinyInteger('process')->default(0)->comment('状态 0-待处理 1-已处理 -1-无需处理');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
