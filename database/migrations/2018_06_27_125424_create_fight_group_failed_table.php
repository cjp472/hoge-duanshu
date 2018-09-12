<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFightGroupFailedTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fight_group_failed', function (Blueprint $table) {
            $table->integer('id',1);
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('order_id',64)->comment('短书订单号');
            $table->text('param')->comment('拼团请求参数');
            $table->tinyInteger('try_times')->comment('重试次数');

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
        Schema::dropIfExists('fight_group_failed');
    }
}
