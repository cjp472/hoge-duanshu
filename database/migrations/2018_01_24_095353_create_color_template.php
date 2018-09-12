<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateColorTemplate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('color_template', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title',32)->comment('配色模板标题');
            $table->char('color',7)->comment('配色色值');
            $table->string('indexpic',256)->nullable()->comment('索引图');
            $table->integer('create_time')->nullable()->comment('创建时间');
            $table->integer('order_id')->nullable()->comment('排序');
            $table->string('type',32)->comment('配色模板使用场景');
            $table->string('class',32)->comment('类');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('color_template');
    }
}
