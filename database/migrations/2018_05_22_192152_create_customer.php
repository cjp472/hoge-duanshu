<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->char('shop_id',18)->nullable();
            $table->string('user_name',32)->nullable()->comment('主体');
            $table->string('contacts',32)->nullable()->comment('联系人');
            $table->string('telephone',32)->nullable()->comment('联系方式');
            $table->integer('intention')->default(0)->comment('意向状态');
            $table->integer('cooperation')->default(0)->comment('合作状态');
            $table->integer('follow_date')->default(0)->comment('下次预约日期');
            $table->integer('customer_id')->default(0)->comment('客户id');
            $table->timestamps();
        });

        Schema::create('customer_follow', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->char('shop_id',16)->nullable();
            $table->integer('date')->nullable()->comment('日期');
            $table->text('content')->nullable()->comment('跟进情况');
            $table->string('follow_user',32)->default('未知');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('customer');
        Schema::drop('customer_follow');
    }
}
