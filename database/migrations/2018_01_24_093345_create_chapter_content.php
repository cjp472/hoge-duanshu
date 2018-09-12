<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChapterContent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chapter_content', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('course_id',12)->comment('课程id');
            $table->string('title',32)->comment('课程标题');
            $table->tinyInteger('is_top')->default(0)->comment('是否置顶');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认');
            $table->timestampsTz();
            $table->index('shop_id');
            $table->index(['shop_id','course_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('chapter_content');
    }
}
