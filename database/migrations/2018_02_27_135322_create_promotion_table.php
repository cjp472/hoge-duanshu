<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePromotionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotion',function (Blueprint $table){
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('promotion_id',32)->comment('推广员id');
            $table->string('visit_id',32)->comment('邀请人id');
            $table->tinyInteger('state')->default(2)->comment('审核状态，0未通过1通过2待审核');
            $table->integer('apply_time')->comment('申请时间');
            $table->integer('add_time')->comment('创建时间');
            $table->tinyInteger('is_delete')->default(0)->comment('是否清退，0-否，1-清退');
            $table->integer('delete_time')->comment('清退时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promotion');
    }
}
