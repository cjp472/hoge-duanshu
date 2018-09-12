<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOpenPlatformPublic extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('open_platform_public', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('appid',32)->comment('appid 授权方appid');
            $table->string('primitive_name',32)->comment('授权方原始id');
            $table->text('access_token')->nullable()->comment('授权方access_token');
            $table->text('refresh_token')->nullable()->comment('授权方refresh_token');
            $table->text('old_refresh_token')->nullable()->comment('60018错误刷新token备用');
            $table->integer('create_time')->comment('创建时间');
            $table->integer('update_time')->comment('修改时间');
            $table->text('authorizer_info')->nullable()->comment('小程序冗余信息');

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
        Schema::drop('open_platform_public');
    }
}
