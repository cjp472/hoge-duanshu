<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePraise extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('praise', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('comment_id')->comment('评论的id');
            $table->char('member_id',32)->comment('会员id');
            $table->integer('praise_num')->default(1)->comment('点赞数');
            $table->integer('praise_time')->comment('点赞时间');

            $table->index('comment_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('praise');
    }
}
