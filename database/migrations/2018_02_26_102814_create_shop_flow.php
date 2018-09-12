<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopFlow extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_flow', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('numberical',32)->comment('存储/流量用量');
            $table->string('remark',32)->comment('类型');
            $table->integer('time')->comment('记录时间');
            $table->float('unit_price',8,2)->comment('单价');
            $table->float('price',8,2)->comment('价格');
            $table->integer('flow_type')->comment('流量类型（1付费0免费）');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('shop_flow');
    }
}
