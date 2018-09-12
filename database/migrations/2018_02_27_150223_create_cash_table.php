<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCashTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cash',function(Blueprint $table){
           $table->increments('id');
           $table->string('member_id',32)->comment('提现人id');
           $table->decimal('cash',10,2)->comment('提现金额');
           $table->integer('cash_time')->comment('提现时间');
           $table->string('type',16)->default('weixin')->comment('提现方式');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cash');
    }
}
