<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('code', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->integer('code_id')->comment('邀请码主表Id');
            $table->char('code',20)->comment('邀请码');
            $table->char('user_id',32)->nullable()->comment('使用会员id');
            $table->string('user_name',32)->nullable()->comment('使用会员昵称');
            $table->string('avater',10000)->nullable()->comment('使用会员头像');
            $table->integer('use_time')->nullable()->comment('使用时间');
            $table->tinyInteger('status')->default(0)->comment('使用状态');
            $table->string('gift_word',256)->nullable()->comment('赠送语');
            $table->tinyInteger('copy')->default(0)->comment('复制状态 0未复制 1已复制');
            $table->index('shop_id');
            $table->index('code');
            $table->index(['shop_id','code_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('code');
    }
}
