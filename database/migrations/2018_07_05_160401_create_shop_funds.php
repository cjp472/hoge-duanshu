<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopFunds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_funds', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->integer('amount')->comment('短书币数量,单位分');
            $table->integer('balance')->comment('余额,单位分');
            $table->char('product_type',12)->comment('商品类型, qcloud_cos:存储, qlound_cdn:流量, token:短书币');
            $table->char('product_name', 64)->comment('商品名称');
            $table->char('type', 12)->comment('类型, income:收入 expend:支出');
            $table->char('transaction_no', 32)->comment('交易号');
            $table->integer('unit_price')->comment('单价');
            $table->float('quantity',12,4)->comment('数量');
            $table->integer('total_price')->comment('总价');
            $table->integer('status')->comment('状态 0:正常 -1:无效');
            $table->char('source', 12)->comment('来源, pre:预发布, production:生产');
            $table->date('date')->comment('日期');
            $table->timestampsTz();

            $table->index(['shop_id', 'date']);
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
