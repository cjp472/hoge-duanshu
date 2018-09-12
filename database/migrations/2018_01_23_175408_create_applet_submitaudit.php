<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppletSubmitaudit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('applet_submitaudit', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('auditid')->comment('微信审核返回的id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('appid',32)->comment('小程序id');
            $table->smallInteger('template_id')->comment('小程序模板');
            $table->string('user_version',32)->comment('用户版本号');
            $table->string('applet_version',10)->comment('用户版本号');
            $table->string('primitive_name',32)->comment('授权方原始名称');
            $table->tinyInteger('status')->default(0)->comment('审核结果状态');
            $table->text('reason')->comment('审核不通过原因');
            $table->integer('create_time')->comment('提交审核时间');
            $table->integer('audit_time')->comment('审核通过时间');
            $table->text('category')->nullable()->comment('体验小程序类目配置');
            $table->text('item_list')->nullable()->comment('提交审核数据');
            $table->text('callback')->nullable()->comment('微信回调数据');
            $table->tinyInteger('is_release')->default(0)->comment('是否发布');
            $table->index('shop_id');
            $table->index(['shop_id','appid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('applet_submitaudit');
    }
}
