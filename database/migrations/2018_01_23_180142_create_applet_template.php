<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppletTemplate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('applet_template', function (Blueprint $table) {
            $table->increments('id')->comment('小程序模板id');
            $table->string('appid',32)->comment('模板小程序id');
            $table->string('title',32)->comment('小程序模板标题');
            $table->smallInteger('template_id')->comment('小程序模板');
            $table->string('user_version',32)->comment('用户版本号');
            $table->integer('create_time')->comment('创建时间');
            $table->string('edition',32)->comment('小程序版本 basic-基础版 advanced-高级版');
            $table->tinyInteger('is_display')->default(1)->comment('是否使用 0-不适用 1-使用');
            $table->index('appid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('applet_template');
    }
}
