<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFeedback extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id')->comment('店铺id');
            $table->char('member_id')->comment('用户Id');
            $table->text('content')->comment('反馈内容');
            $table->string('contact_way',18)->comment('联系方式');
            $table->integer('feedback_time')->comment('反馈时间');
            $table->integer('replay_time')->comment('回复时间');

            $table->index('shop_id');
            $table->index(['member_id','shop_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('feedback');
    }
}
