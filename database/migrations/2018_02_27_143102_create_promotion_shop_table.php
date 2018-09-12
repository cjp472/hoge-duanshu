<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromotionShopTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotion_shop',function(Blueprint $table){
           $table->integer('id');
           $table->char('shop_id',18);
           $table->tinyInteger('is_check')->default(1)->comment('是否审核，0-不审核，1-审核');
           $table->string('re_url',256)->comment('招募url');
           $table->string('re_title',64)->comment('招募标题');
           $table->text('re_plan')->comment('招募详情');
           $table->tinyInteger('money_percent')->default(0)->comment('佣金比例');
           $table->tinyInteger('is_visit')->default(0)->comment('是否好友邀请,0-否，1-是');
           $table->tinyInteger('visit_percent')->default(0)->comment('邀请比例');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promotion_shop');
    }
}
