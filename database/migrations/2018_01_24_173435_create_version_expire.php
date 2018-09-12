<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVersionExpire extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('version_expire', function (Blueprint $table) {
            $table->increments('id');
            $table->char('hashid',18);
            $table->string('version',16)->default('advanced')->comment('版本');
            $table->integer('start')->default(0)->comment('开始时间');
            $table->integer('expire')->default(0)->comment('结束时间');
            $table->index(['hashid','version']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('version_expire');
    }
}
