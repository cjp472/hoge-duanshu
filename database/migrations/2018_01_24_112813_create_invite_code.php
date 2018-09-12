<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInviteCode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invite_code', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('title',128)->comment('标题');
            $table->string('type',16)->comment('邀请码类型 self自建 share分享赠送');
            $table->integer('total_num')->default(0)->comment('总数');
            $table->integer('use_num')->default(0)->comment('已使用数');
            $table->integer('start_time')->comment('启用时间');
            $table->integer('end_time')->comment('结束时间');
            $table->string('instruction',256)->nullable()->comment('使用说明');
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type',16)->comment('内容类型');
            $table->string('content_title',256)->nullable()->comment('内容标题');
            $table->string('content_indexpic',256)->nullable()->comment('内容索引图');
            $table->decimal('price',8,2)->comment('内容价格');
            $table->char('user_id',32)->comment('使用人id');
            $table->string('user_name',32)->comment('使用人昵称');
            $table->string('avatar',256)->comment('使用人头像');
            $table->integer('order_id')->comment('排序');
            $table->integer('buy_time')->comment('分享邀请码购买时间');
            $table->timestampsTz();
            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('invite_code');
    }
}
