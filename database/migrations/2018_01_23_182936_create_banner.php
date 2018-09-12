<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBanner extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banner', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('title',64)->comment('轮转图标题');
            $table->string('indexpic',255)->comment('轮转图');
            $table->string('link',255)->comment('跳转链接');
            $table->tinyInteger('state')->default(0)->comment('状态');
            $table->integer('up_time')->comment('上架时间');
            $table->integer('down_time')->comment('下架时间');
            $table->integer('create_user')->comment('创建用户');
            $table->integer('update_user')->comment('更新用户');
            $table->integer('create_time')->comment('创建时间');
            $table->integer('update_time')->comment('更新时间');
            $table->tinyInteger('top')->default(0)->comment('是否置顶');
            $table->tinyInteger('is_lock')->default(0)->comment('是否锁定');
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
        Schema::drop('banner');
    }
}
