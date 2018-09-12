<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMaterial extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('material', function (Blueprint $table) {
            $table->increments('id');
            $table->char('user_id',32)->comment('操作人名称');
            $table->string('union_id',32)->nullable()->comment('用户绑定的微信号');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('sign',18)->comment('素材类型 courseware-课件 manage-管理素材');
            $table->string('title',64)->comment('素材标题');
            $table->string('type',32)->comment('素材类型');
            $table->string('content',1000)->comment('素材内容');
            $table->tinyInteger('is_display')->default(1)->comment('是否显示');
            $table->tinyInteger('is_top')->default(0)->comment('是否置顶');
            $table->timestampsTz();

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
        Schema::drop('material');
    }
}
