<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateErrorLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->comment('操作人名称');
            $table->string('user_name',32)->nullable()->comment('操作人名称');
            $table->string('type',16)->nullable()->comment('操作类型');
            $table->string('classtype',32)->nullable()->comment('操作类型');
            $table->string('route',200)->comment('操作路由');
            $table->text('input_data')->nullable()->comment('输入数据');
            $table->text('error')->nullable()->comment('错误内容');
            $table->string('ip',15)->nullable()->comment('操作人ip');
            $table->text('user_agent')->nullable()->comment('操作人浏览器信息');
            $table->integer('time')->comment('时间');
            $table->string('source',16)->default('back')->comment('来源 后端back 前端front');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('error_logs');
    }
}
