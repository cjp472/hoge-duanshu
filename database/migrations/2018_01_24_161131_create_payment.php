<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePayment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('user_id',32)->comment('会员id');
            $table->string('nickname',32)->nullable()->comment('昵称');
            $table->string('avatar',256)->nullable()->comment('头像');
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type',32)->comment('内容类型');
            $table->string('content_title',256)->nullable()->comment('内容标题');
            $table->string('content_indexpic',256)->nullable()->comment('内容索引图');
            $table->char('order_id',22)->comment('订单id');
            $table->tinyInteger('payment_type')->comment('1-支付购买 2- 赠送领取 3- 自建邀请码，4-免费订阅');
            $table->char('share_user')->nullable()->comment('分享人');
            $table->string('share_user_name',32)->nullable()->comment('分享人昵称');
            $table->integer('order_time')->comment('下单时间');
            $table->decimal('price',8,2)->comment('订单价格');
            $table->string('source',32)->comment('订单渠道 h5 applet');
            $table->integer('expire_time')->comment('到期时间');

            $table->index('shop_id');
            $table->index(['content_id','content_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('payment');
    }
}
