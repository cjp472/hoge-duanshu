<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppletRelease extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('applet_release', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sid')->comment('审核表的id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('appid',32)->comment('小程序id');
            $table->smallInteger('template_id')->comment('小程序模板');
            $table->string('user_version',32)->comment('用户版本号');
            $table->string('applet_version',10)->comment('用户版本号');
            $table->text('category')->nullable()->comment('体验小程序类目配置');
            $table->integer('release_time')->comment('发布时间');
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
        Schema::drop('applet_release');
    }
}
