<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVideo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('video', function (Blueprint $table) {
            $table->increments('id');
            $table->char('content_id',12);
            $table->string('patch',255);
            $table->longText('content');
            $table->string('file_id',64);
            $table->string('file_name',255);
            $table->tinyInteger('transcode');
            $table->string('size',32);
            $table->string('test_file_id',64)->nullable();
            $table->string('test_file_name',255)->nullable();
            $table->string('test_size',32)->default(0);

            $table->index('content_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('video');
    }
}
