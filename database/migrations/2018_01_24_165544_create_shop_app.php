<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopApp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_app', function (Blueprint $table) {
            $table->increments('id');

            $table->char('shop_id',18);
            $table->string('appkey',32);
            $table->string('appsecret',128);
            $table->text('model_slug');
            $table->integer('create_time');
            $table->string('group_id',1000);

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
        Schema::drop('shop_app');
    }
}
