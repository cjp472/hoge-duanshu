<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInviteCardTemplate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invite_card_template', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title',128)->comment('邀请卡模板标题');
            $table->string('indexpic',256)->comment('邀请卡索引图');
            $table->string('backgroundpic',256)->comment('背景图片');
            $table->string('brief',128)->nullable()->comment('模板描述');
            $table->text('content')->comment('模板描述');
            $table->integer('create_time')->comment('创建时间');
            $table->tinyInteger('status')->default(1)->comment('显示状态');
            $table->integer('order_id')->comment('显示顺序');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('invite_card_template');
    }
}
