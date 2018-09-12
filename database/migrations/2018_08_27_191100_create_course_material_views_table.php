<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCourseMaterialViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('course_material_views', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id', 18);
            $table->char('course_id', 64)->comment('课程id');
            $table->integer('material_id')->comment('学习资料id');
            $table->integer('view_time')->comment('浏览时间');
            $table->char('member_id', 32)->comment('浏览用户id');
            $table->string('source', 12)->comment('用户来源');

            $table->index('shop_id');
            $table->index('course_id');
            $table->index('material_id');
            $table->index('member_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('course_material_views');
    }
}
