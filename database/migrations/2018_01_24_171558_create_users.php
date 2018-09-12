<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name',255);
            $table->string('email',255)->nullable();
            $table->string('password',255);
            $table->string('remember_token',100);
            $table->string('avatar',256)->nullable();
            $table->string('username',32);
            $table->string('mobile',15)->nullable();
            $table->integer('create_user')->nullable();
            $table->string('source')->nullable();
            $table->tinyInteger('active')->default(1);
            $table->integer('login_time')->nullable();
            $table->timestampsTz();

            $table->index('mobile');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }
}
