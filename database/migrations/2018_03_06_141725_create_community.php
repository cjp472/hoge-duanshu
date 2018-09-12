<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCommunity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('community', function (Blueprint $table) {
            $table->increments('id');
            $table->char('hashid',12);
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('title',32)->comment('名称');
            $table->string('brief',64)->comment('描述');
            $table->text('indexpic')->comment('索引图');
            $table->string('authority',18)->comment('发帖权限（全部-all,管理员-admin），默认admin');
            $table->integer('pay_type')->comment('是否收费（是-1，否-0）');
            $table->decimal('price',10,2)->comment('价格');
            $table->integer('display')->comment('显示1/隐藏0');
            $table->integer('member_num')->comment('加入社群成员总数');

            $table->index(['shop_id','hashid']);
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
        Schema::drop('community');
    }
}
