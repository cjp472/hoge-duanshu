<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClassContent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('class_content', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('course_id',12)->comment('课程id');
            $table->integer('chapter_id')->comment('章节id');
            $table->string('title',32)->comment('课程标题');
            $table->text('content')->comment('课程内容');
            $table->integer('view_count')->default(0)->comment('访问量');
            $table->tinyInteger('is_free')->default(0)->comment('是否试看');
            $table->tinyInteger('is_top')->default(0)->comment('是否置顶');
            $table->timestampsTz();
            $table->index('shop_id');
            $table->index(['shop_id','course_id','chapter_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('class_content');
    }
}
