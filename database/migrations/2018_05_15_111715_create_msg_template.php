<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMsgTemplate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('msg_template', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title',256)->nullable()->comment('模板标题');
            $table->text('content')->nullable()->comment('短信模板内容');
            $table->integer('create_time')->nullable()->comment('创建时间');
            $table->smallInteger('status')->default(0)->comment('审核状态');
            $table->integer('user_id')->unsigned()->comment('操作人名称');
            $table->string('user_name',32)->nullable()->comment('操作人名称');
            $table->timestampsTz();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
