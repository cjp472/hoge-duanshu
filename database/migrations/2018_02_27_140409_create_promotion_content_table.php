<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromotionContentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotion_content',function (Blueprint $table){
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type',32)->comment('内容类型');
            $table->string('content_title',64)->comment('内容标题');
            $table->tinyInteger('money_percent')->comment('佣金比例');
            $table->tinyInteger('visit_percent')->comment('邀请比例');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promotion_content');
    }
}
