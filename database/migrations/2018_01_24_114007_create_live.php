<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLive extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('live', function (Blueprint $table) {
            $table->increments('id');
            $table->char('content_id',12)->comment('内容id');
            $table->string('brief',256)->nullable()->comment('简介');
            $table->string('live_indexpic')->nullable()->comment('直播索引图');
            $table->integer('start_time')->comment('开始时间');
            $table->integer('end_time')->comment('结束时间');
            $table->tinyInteger('live_type')->comment('直播类型');
            $table->text('live_describe')->nullable()->comment('直播描述');
            $table->tinyInteger('live_state')->default(0)->comment('直播状态');
            $table->text('live_person')->comment('直播人员');
            $table->string('file_id',64)->comment('直播素材id');
            $table->string('file_name',128)->nullable()->comment('直播素材标题');
            $table->tinyInteger('gag')->default(0)->comment('是否禁用');
            $table->tinyInteger('manage')->default(0)->comment('开始管理模式');
            $table->text('live_flow')->nullable()->comment('直播流');
            $table->index('content_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('live');
    }
}
