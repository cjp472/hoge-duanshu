<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTryUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('try_user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('mobile',15);
            $table->integer('create_time');
            $table->string('name',32);
            $table->string('compony',64);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('try_user');
    }
}
