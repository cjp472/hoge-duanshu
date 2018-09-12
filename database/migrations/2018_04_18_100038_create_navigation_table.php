<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNavigationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('navigation', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('title',12)->comment('导航标题');
            $table->string('index_pic',255)->comment('导航图');
            $table->string('link',255)->comment('跳转链接');
            $table->tinyInteger('status')->default(1)->comment('状态');
            $table->integer('create_time')->comment('创建时间');
            $table->integer('order_id')->comment('排序');

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
        Schema::drop('navigation');
    }
}
