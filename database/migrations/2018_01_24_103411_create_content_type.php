<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContentType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('content_type', function (Blueprint $table) {
            $table->increments('id');
            $table->char('content_id',12)->comment('内容id');
            $table->integer('type_id')->comment('分类id');
            $table->string('type',32)->comment('内容类型:article video audio live column course');
            $table->integer('create_time')->comment('创建时间');
            $table->index('type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('content_type');
    }
}
