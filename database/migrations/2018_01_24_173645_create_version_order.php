<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVersionOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('version_order', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18);
            $table->string('product_id',32)->comment('商品id');
            $table->string('product_name',32)->nullable()->comment('商品标题');
            $table->string('brief',256)->nullable()->comment('商品描述');
            $table->string('thumb',256)->nullable()->comment('商品预览图');
            $table->string('type',12)->comment('商品类型');
            $table->string('category',255)->nullable()->comment('商品分类');
            $table->string('sku',255)->nullable()->comment('商品规格');
            $table->double('unit_price',8,2)->default(0.00)->comment('商品单价');
            $table->integer('quantity')->default(1)->comment('商品数量');
            $table->double('total',8,2)->default(0.00)->comment('商品总价');
            $table->text('meta')->nullable()->comment('商品元数据');
            $table->string('order_no',255)->nullable()->comment('订单编号');
            $table->integer('success_time')->nullable()->comment('成功时间');
            $table->integer('create_time');

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
        Schema::drop('version_order');
    }
}
