<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopStorageFlux extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_storage_flux', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->bigInteger('value')->comment('使用量,单位byte');
            $table->char('type', 12)->comment('类型, qcloud_cos:存储, qlound_cdn:流量');
            $table->char('source', 12)->comment('来源, pre:预发布, production:生产');
            $table->date('date')->comment('结算日期');
            $table->timestampsTz();

            $table->index(['shop_id', 'date']);
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
