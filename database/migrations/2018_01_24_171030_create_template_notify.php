<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTemplateNotify extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('template_notify', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id')->comment('店铺id');
            $table->string('title',32);
            $table->string('send_name',32);
            $table->string('content',256);

            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('template_notify');
    }
}
