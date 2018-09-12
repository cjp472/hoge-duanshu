<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdminMaterial extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admin_material', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->integer('user_id')->unsigned()->comment('用户id');
            $table->string('title',32)->nullable()->comment('标题');
            $table->text('content')->nullable()->comment('内容（文字类型）');
            $table->string('type',12)->comment('类型:图片-image 文字-text 音频-audio');
            $table->string('url',256)->nullable()->comment('图片地址');
            $table->string('audio',256)->nullable()->comment('音频地址');
            $table->integer('create_time')->nullable()->comment('创建时间');
            $table->integer('update_time')->nullable()->comment('更新时间');
            $table->tinyInteger('status')->nullable()->comment('0-隐藏 1-显示');
            $table->integer('top')->default(0)->comment('0-未置顶 1-置顶');
            $table->index('shop_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('admin_material');
    }
}
