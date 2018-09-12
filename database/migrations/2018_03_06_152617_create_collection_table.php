<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCollectionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('collection', function (Blueprint $table) {
            $table->increments('id');
            $table->string('content_id',12)->comment('收藏的内容id');
            $table->string('content_type',16)->comment('收藏的内容类型');
            $table->string('member_id',64)->comment('收藏人id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->timestamp('collection_time')->comment('收藏时间');

            $table->index(['shop_id','member_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('collection');
    }
}
