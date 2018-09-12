<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCourse extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('course', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('hashid',12)->comment('课程id');
            $table->string('title',255)->comment('课程标题');
            $table->string('course_type',32)->comment('课程类型 audio video');
            $table->text('lecturer')->nullable()->comment('讲师');
            $table->tinyInteger('is_finish')->default(0)->comment('是否完结');
            $table->tinyInteger('state')->default(0)->comment('上架状态 0未上架 1正常');
            $table->text('brief')->nullable()->comment('简介');
            $table->text('describe')->nullable()->comment('描述');
            $table->string('indexpic',256)->nullable()->comment('索引图');
            $table->decimal('price',8,2)->default('0.00')->comment('价格');
            $table->tinyInteger('pay_type')->comment('是否收费');
            $table->integer('subscribe')->default(0)->comment('订阅数');
            $table->integer('view_count')->default(0)->comment('浏览数');
            $table->integer('create_time')->comment('创建时间');
            $table->integer('update_time')->nullable()->comment('更新时间');
            $table->integer('create_user')->comment('创建用户');
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
        Schema::drop('course');
    }
}
