<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSdkTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sdk',function (Blueprint $table){
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('name',50)->comment('应用名称');
            $table->string('index_pic',255)->comment('应用图片');
            $table->string('app_id',20)->comment('app_id');
            $table->string('app_secret',50)->comment('app_secret');
            $table->string('platform',255)->comment('平台信息');
            $table->string('purpose',20)->comment('来源');

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
        Schema::dropIfExists('sdk');
    }
}
