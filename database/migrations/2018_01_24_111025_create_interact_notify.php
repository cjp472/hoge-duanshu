<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInteractNotify extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('interact_notify', function (Blueprint $table) {
            $table->increments('id');
            $table->char('member_id',32)->comment('消息通知用户id');
            $table->char('interact_id')->comment('互动人id');
            $table->string('interact_name',64)->comment('互动人名称');
            $table->string('interact_avatar',256)->nullable()->comment('互动人头像');
            $table->string('type',16)->comment('互动类型 praise reply');
            $table->char('content_id',12)->comment('互动内容id');
            $table->string('content_type',16)->comment('互动内容类型');
            $table->string('content_title',256)->nullable()->comment('互动内容标题');
            $table->string('content_indexpic',256)->nullable()->comment('互动内容索引图');
            $table->string('message',256)->comment('回复内容');
            $table->integer('interact_time')->comment('互动时间');
            $table->index('interact_id');
            $table->index(['content_id','content_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('interact_notify');
    }
}
