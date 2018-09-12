<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('order_id',22)->comment('订单id');
            $table->char('user_id')->comment('会员id');
            $table->string('nickname',32)->nullable()->comment('昵称');
            $table->string('avatar',256)->nullable()->comment('头像');
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type',32)->comment('内容类型');
            $table->string('content_title',256)->nullable()->comment('内容标题');
            $table->string('content_indexpic',256)->nullable()->comment('内容索引图');
            $table->char('pay_id',32)->nullable()->comment('支付号');
            $table->tinyInteger('pay_status')->default(0)->comment('支付状态');
            $table->integer('pay_time')->nullable()->comment('支付时间');
            $table->integer('order_type')->comment('订单类型 ');
            $table->integer('order_time')->comment('下单时间');
            $table->decimal('price',8,2)->comment('订单价格');
            $table->tinyInteger('gift_status')->default(0)->comment('赠送状态');
            $table->integer('number')->default(1)->comment('购买数量');
            $table->string('center_order_no',64)->comment('订单中心号码');
            $table->string('channel',16)->default('production')->comment('订单来源 production-生产 pre-预发布');
            $table->string('source',32)->comment('订单渠道 h5 applet');

            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('order');
    }
}
