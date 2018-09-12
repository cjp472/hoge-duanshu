<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderMarketingActivityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_marketing_activity', function (Blueprint $table) {
            $table->integer('id',1);
            $table->string('order_no',64)->default('')->comment('订单中心订单号');
            $table->string('order_id',22)->default('')->comment('短书内部订单号');
            $table->string('marketing_activity_id',64)->default('')->comment('营销活动id');
            $table->string('marketing_activity_type',64)->default('fight_group')->comment('营销活动类型，fight_group-拼团');

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
        Schema::dropIfExists('order_marketing_activity');
    }
}
