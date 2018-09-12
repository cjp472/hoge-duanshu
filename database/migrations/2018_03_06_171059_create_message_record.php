<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMessageRecord extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('message_record', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18);
            $table->string('user',100)->comment('使用人');
            $table->tinyInteger('type')->comment('1消耗 2充值');
            $table->integer('number');
            $table->integer('create_time');

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
        Schema::drop('message_record');
    }
}
