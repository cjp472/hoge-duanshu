<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopColor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_color', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18);
            $table->integer('color_id');
            $table->string('type',32)->comment('类型 h5 applet');
            $table->integer('create_time');

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
        Schema::drop('shop_color');
    }
}
