<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUvPvToCourseMaterial extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('course_material', function (Blueprint $table) {
            $table->integer('view_count')->default(0)->comment('阅读数');
            $table->integer('unique_member')->default(0)->comment('人次');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('course_material', function (Blueprint $table) {
            //
        });
    }
}
