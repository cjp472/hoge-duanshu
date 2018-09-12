<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoursePreViewersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('course_pre_viewer', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('course_id', 64)->comment('所属课程');
            $table->char('member_id', 32)->comment('会员');
            $table->integer('pre_view_num')->default(0)->comment('试学次数');
            $table->dateTime('last_studied_time')->nullable()->comment('最近学习时间');

            $table->unique(['course_id', 'member_id']);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('course_pre_viewers');
    }
}
