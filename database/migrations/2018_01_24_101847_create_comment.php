<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateComment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comment', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type',32)->comment('内容类型');
            $table->integer('fid')->default(0)->comment('父id');
            $table->char('member_id')->comment('评论用户id');
            $table->text('content')->comment('评论内容');
            $table->integer('comment_time')->comment('评论时间');
            $table->integer('praise')->default(0)->comment('点赞数');
            $table->tinyInteger('status')->default(1)->comment('状态 0隐藏 1显示');
            $table->tinyInteger('choice')->default(0)->comment('精选状态');
            $table->index('content_id');
            $table->index(['shop_id','content_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('comment');
    }
}
