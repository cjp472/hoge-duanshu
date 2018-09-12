<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLimitPurchase extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('limit_purchase', function (Blueprint $table) {
            $table->increments('id');
            $table->char('hashid',32);
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('title',32)->comment('活动标题');
            $table->text('describe')->comment('活动描述');
            $table->text('indexpic')->comment('活动封面');
            $table->integer('start_time')->comment('开始时间');
            $table->integer('end_time')->comment('结束时间');
            $table->integer('range')->comment('范围（1全场，2指定）');
            $table->float('discount')->comment('折扣');
            $table->float('condition')->comment('使用条件');
            $table->float('shelf')->comment('上架状态：0-待上架1-上架2-下架');
            $table->integer('up_time')->comment('上架时间');
            $table->integer('switch')->default(1)->comment('活动开关');
            $table->text('contents')->comment('指定内容（1-全场通用,其他的序列化存储）');
            $table->index('shop_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('limit_purchase');
    }
}
