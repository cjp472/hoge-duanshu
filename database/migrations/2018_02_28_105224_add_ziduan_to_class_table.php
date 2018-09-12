<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddZiduanToClassTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('class_content', function (Blueprint $table) {
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type',16)->comment('内容分类，article-图文，audio-音频，video-视频');
            $table->string('brief',64)->comment('课时简介');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
