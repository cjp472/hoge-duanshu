<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAudio extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audio', function (Blueprint $table) {
            $table->increments('id');
            $table->char('content_id',12)->comment('内容id');
            $table->text('content')->comment('内容');
            $table->string('url',255)->comment('音频链接');
            $table->string('test_url',255)->nullable()->comment('试听音频链接');
            $table->string('file_name',256)->comment('音频标题');
            $table->string('test_file_name',256)->nullable()->comment('试听音频标题');
            $table->string('size',32)->comment('音频大小');
            $table->string('test_size',32)->nullable()->comment('试听音频大小');
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
        Schema::drop('audio');
    }
}
