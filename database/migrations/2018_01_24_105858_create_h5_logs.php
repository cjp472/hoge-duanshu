<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateH5Logs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('h5_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->char('user_id',32)->comment('操作人名称');
            $table->string('user_name',32)->nullable()->comment('操作人名称');
            $table->string('type',16)->nullable()->comment('操作类型');
            $table->string('title',256)->comment('操作标题');
            $table->string('route',200)->comment('操作路由');
            $table->text('input_data')->nullable()->comment('输入数据');
            $table->text('output_data')->nullable()->comment('输出数据');
            $table->string('ip',15)->nullable()->comment('操作人ip');
            $table->text('user_agent')->nullable()->comment('操作人浏览器信息');
            $table->integer('time')->comment('时间');
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
        Schema::drop('h5_logs');
    }
}
