<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromotionRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotion_record',function(Blueprint $table){
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('order_id',64)->comment('短书订单号');
            $table->string('promotion_id',32)->comment('推广员id');
            $table->string('visit_id',32)->comment('邀请人id');
            $table->string('buy_id',32)->comment('	购买者id');
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type',16)->comment('内容类型');
            $table->string('content_title',64)->comment('内容标题');
            $table->decimal('deal_money',10,2)->comment('交易金额');
            $table->tinyInteger('money_percent')->comment('佣金比例');
            $table->tinyInteger('visit_percent')->comment('邀请比例');
            $table->tinyInteger('state')->comment('结算状态，0-未结算，1-已结算');
            $table->integer('create_time')->comment('创建时间');
            $table->integer('finish_time')->comment('完成时间');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promotion_record');
    }
}
