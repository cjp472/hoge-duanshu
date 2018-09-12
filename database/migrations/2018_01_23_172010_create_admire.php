<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdmire extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admire', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('content_id',12)->comment('直播内容id');
            $table->char('member_id',32)->comment('赞赏会员id');
            $table->char('lecturer',32)->comment('讲师id');
            $table->decimal('money')->comment('赏款');
            $table->integer('admire_time')->comment('打赏时间');
            $table->string('center_order_no',64)->comment('订单中心号');
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
        Schema::drop('admire');
    }
}
