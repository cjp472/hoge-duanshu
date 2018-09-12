<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShop extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop', function (Blueprint $table) {
            $table->increments('id');

            $table->char('hashid',18);
            $table->string('title',64);
            $table->string('brief',256);
            $table->integer('create_time');
            $table->string('mch_id',32);
            $table->string('test_mch_id',32);
            $table->tinyInteger('status')->default(0)->comment('0-正常 1-关闭');
            $table->string('version',32)->default('basic')->comment('版本');
            $table->string('verify_status',16)->default('none')->comment('认证状态');
            $table->string('verify_first_type',16)->comment('认证类型');
            $table->string('withdraw_account',16)->comment('提现账户');
            $table->string('account_id',64)->comment('提现账户');
            $table->tinyInteger('is_black')->comment('是否黑名单');
            $table->string('applet_version',32)->default('basic')->comment('小程序版本');

            $table->index('hashid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('shop');
    }
}
