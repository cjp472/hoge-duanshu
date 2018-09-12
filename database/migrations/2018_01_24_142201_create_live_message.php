<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLiveMessage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('live_message', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('content_id',12)->comment('内容id');
            $table->char('member_id',32)->comment('会员id');
            $table->text('message')->nullable()->comment('讨论内容');
            $table->tinyInteger('type')->default(1);
            $table->integer('time')->comment('发言时间');
            $table->string('tag',64)->nullable()->comment('用户身份');
            $table->tinyInteger('problem')->default(0)->comment('是否是提问');
            $table->string('nick_name',32)->nullable()->comment('昵称');
            $table->string('avatar',255)->nullable()->comment('头像');
            $table->text('audio')->nullable()->comment('语音内容');
            $table->string('indexpic',255)->nullable()->comment('图片url');
            $table->integer('pid')->default(0)->comment('上级id');
            $table->tinyInteger('problem_state')->default(0)->comment('提问状态');
            $table->tinyInteger('is_del')->default(0)->comment('是否撤销');
            $table->index('shop_id');
            $table->index('content_id');
            $table->index(['content_id','shop_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('live_message');
    }
}
