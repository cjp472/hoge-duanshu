<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopMultiple extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_multiple', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18);
            $table->integer('multiple')->comment('倍数');
            $table->text('range')->comment('作用范围 1浏览数 2订阅数 3在线人数');

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
        Schema::drop('shop_multipie');
    }
}
