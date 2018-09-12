<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMemberCard extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('member_card', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('hashid',12)->comment('会员卡Id');
            $table->string('title',32)->comment('会员卡标题');
            $table->string('style',32)->comment('风格');
            $table->tinyInteger('expire')->comment('有效期');
            $table->tinyInteger('card_type')->default(1)->comment('使用范围: 1-全场通用 2-指定商品');
            $table->string('discount',12)->comment('折扣');
            $table->decimal('price',8,2)->comment('会员卡价格');
            $table->text('use_notice')->comment('使用须知');
            $table->integer('subscribe')->default(0)->comment('购买人数');
            $table->integer('view_count')->default(0)->comment('浏览量');
            $table->integer('status')->default(1)->comment('上架状态');
            $table->integer('up_time')->comment('上架时间');
            $table->text('discount_explain')->comment('权益说明');
            $table->text('purchase_notice')->comment('购买须知');
            $table->integer('top')->comment('是否置顶');
            $table->integer('order_id')->comment('排序id');
            $table->integer('is_del')->comment('是否删除（1是0否）');
            $table->timestampsTz();

            $table->index('shop_id');
            $table->index(['shop_id','hashid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('member_card');
    }
}
