<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopProtocolTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_protocol', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('p_id')->comment('协议id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->integer('create_time')->comment('创建时间');
            $table->string('content',255)->comment('协议其他信息');
            $table->tinyInteger('status')->default(1)->comment('协议状态');

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
        Schema::drop('shop_protocol');
    }
}
