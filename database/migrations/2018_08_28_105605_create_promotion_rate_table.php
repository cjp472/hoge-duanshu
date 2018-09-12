<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromotionRateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotion_rate', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id', 18)->comment('店铺id');
            $table->integer('promoter_rate')->default(0)->comment('推广佣金比例');
            $table->integer('invite_rate')->default(0)->comment('邀请佣金比例');
            $table->integer('promotion_content_id')->default(0)->comment('推广商品id, 迁移数据用, 之后删除');
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
