<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHelpCenterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('help_center', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title',200)->comment('标题');
            $table->string('url',200)->comment('链接');
            $table->tinyInteger('is_display')->comment('是否隐藏');
            $table->integer('sort_no')->comment('排序');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('help_center');
    }
}
