<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserMsgTemplate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_msg_template', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->nullable()->comment('店铺id');
            $table->string('title',256)->nullable()->comment('模板标题');
            $table->text('content')->nullable()->comment('短信模板内容');
            $table->integer('apply_time')->nullable()->comment('申请时间');
            $table->smallInteger('status')->default(0)->comment('审核状态 0-待审核 1-审核中 2-已通过  3-已驳回 4-已封禁');
            $table->smallInteger('template_id')->default(0)->comment('来源 0-自建 其他-系统模板id');
            $table->timestampsTz();

            $table->index('shop_id');
            $table->index('status');
        });//
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
