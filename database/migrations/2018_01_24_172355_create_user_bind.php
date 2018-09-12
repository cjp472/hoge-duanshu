<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserBind extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_bind', function (Blueprint $table) {
            $table->increments('id');
            $table->string('openid',256);
            $table->string('source',32)->default('wechat');
            $table->string('nickname',32)->nullable();
            $table->string('avatar',256)->nullable();
            $table->tinyInteger('sex')->default(0);
            $table->integer('user_id');
            $table->integer('create_time');
            $table->integer('bind_time');
            $table->string('ip',15);
            $table->string('agent',256)->nullable();
            $table->string('unionid',256)->nullable();
            $table->text('backup')->nullable();

            $table->index(['source','openid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('user_bind');
    }
}
