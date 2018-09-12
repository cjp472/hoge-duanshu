<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdmireOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admire_order', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('order_id',64)->comment('订单号');
            $table->char('user_id',32)->comment('赞赏会员id');
            $table->string('nickname',128)->comment('赞赏人昵称');
            $table->string('avatar',256)->comment('赞赏人头像');
            $table->char('content_id',12)->comment('直播内容id');
            $table->string('content_type',32)->comment('直播内容类型live');
            $table->char('lecturer',32)->comment('讲师id');
            $table->string('lecturer_name',32)->comment('讲师昵称');
            $table->string('pay_id',64)->nullable()->comment('支付号');
            $table->tinyInteger('pay_status')->nullable()->comment('支付状态');
            $table->integer('pay_time')->nullable()->comment('支付时间');
            $table->decimal('price',8,2)->defalt(0.00)->comment('金额');
            $table->string('center_order_no',64)->nullable()->comment('订单中心号');
            $table->string('channel',32)->default('production')->comment('订单来源:production-线上 pre-预发布');
            $table->index('shop_id');
            $table->index(['shop_id','content_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('admire_order');
    }
}
