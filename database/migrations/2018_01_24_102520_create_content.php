<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('content', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('hashid',12)->comment('内容id');
            $table->string('title',255)->comment('内容标题');
            $table->string('indexpic',256)->nullable()->comment('索引图');
            $table->tinyInteger('payment_type')->comment('付费类型');
            $table->integer('column_id')->default(0)->comment('所属专栏');
            $table->decimal('price',8,2)->default('0.00')->comment('价格');
            $table->integer('up_time')->nullable()->comment('上架时间');
            $table->integer('create_time')->comment('创建时间');
            $table->integer('update_time')->nullable()->comment('更新时间');
            $table->integer('create_user')->comment('创建用户');
            $table->integer('update_user')->comment('更新用户');
            $table->tinyInteger('state')->default(0)->comment('上架状态 0未上架 1正常');
            $table->tinyInteger('display')->default(1)->comment('显示状态 0不显示 1显示');
            $table->string('type',32)->comment('内容类型:article video audio live column course');
            $table->integer('comment_count')->default(0)->comment('评论数');
            $table->integer('view_count')->default(0)->comment('浏览数');
            $table->integer('subscribe')->default(0)->comment('订阅数');
            $table->string('brief',256)->nullable()->comment('简介');
            $table->integer('play_count')->default(0)->comment('播放量');
            $table->integer('end_play_count')->default(0)->comment('完播量');
            $table->integer('share_count')->default(0)->comment('分享量');
            $table->tinyInteger('is_lock')->default(0)->comment('是否锁定');
            $table->index('shop_id');
            $table->index(['shop_id','column_id']);
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
        Schema::drop('content');
    }
}
