<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('column', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('hashid',12)->comment('专栏id');
            $table->string('title',255)->comment('专栏标题');
            $table->string('indexpic',256)->nullable()->comment('索引图');
            $table->string('brief',256)->nullable()->comment('简介');
            $table->text('describe')->nullable()->comment('描述');
            $table->decimal('price',8,2)->default('0.00')->comment('价格');
            $table->tinyInteger('state')->default(0)->comment('上架状态 0未上架 1正常');
            $table->tinyInteger('display')->default(1)->comment('显示状态 0不显示 1显示');
            $table->integer('create_time')->comment('创建时间');
            $table->integer('update_time')->nullable()->comment('更新时间');
            $table->integer('create_user')->comment('创建用户');
            $table->integer('update_user')->comment('更新用户');
            $table->tinyInteger('finish')->default(0)->comment('是否完结');
            $table->integer('subscribe')->default(0)->comment('订阅数');
            $table->tinyInteger('top')->default(0)->comment('置顶状态');
            $table->tinyInteger('charge')->comment('是否收费');
            $table->tinyInteger('is_lock')->default(0)->comment('是否锁定');
            $table->index('shop_id');
            $table->index('hashid');
            $table->index(['shop_id','hashid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('column');
    }
}
