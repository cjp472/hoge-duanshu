<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppContent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('app_content', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type',32)->comment('内容类型');
            $table->string('app_content_id',128)->comment('app端内容id');
            $table->tinyInteger('source')->comment('内容来源 1-叮当');
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
        Schema::drop('app_content');
    }
}
