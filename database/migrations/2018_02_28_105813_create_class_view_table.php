<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClassViewTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('class_views', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18);
            $table->char('course_id',12)->comment('课程id');
            $table->integer('chapter_id')->comment('章节id');
            $table->integer('class_id')->comment('课时id');
            $table->integer('view_time')->comment('浏览时间');
            $table->char('member_id',32)->comment('浏览用户id');
            $table->string('source',12)->comment('用户来源');
            $table->string('user_agent',1000)->nullable();
            $table->string('ip',15);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('class_views');
    }
}
