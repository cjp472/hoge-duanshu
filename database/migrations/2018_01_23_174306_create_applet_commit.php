<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppletCommit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('applet_commit', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('appid',32)->comment('小程序id');
            $table->smallInteger('template_id')->comment('小程序模板');
            $table->string('user_version',32)->comment('用户版本号');
            $table->integer('create_time')->comment('创建时间');
            $table->text('category')->nullable()->comment('体验小程序类目配置');
            $table->text('value')->nullable()->comment('其他数据冗余');
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
        Schema::drop('applet_commit');
    }
}
