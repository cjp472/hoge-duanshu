<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSyncOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sync_order', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18);
            $table->char('uid',32)->comment('会员id');
            $table->char('content_id',12)->comment('内容id');
            $table->string('group_id',32);

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
        Schema::drop('sync_order');
    }
}
