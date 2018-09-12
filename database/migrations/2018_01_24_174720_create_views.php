<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateViews extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('views', function (Blueprint $table) {
            $table->increments('id');
            $table->char('content_id',12);
            $table->string('content_type',15);
            $table->string('content_title',256)->nullable();
            $table->string('content_column',12)->nullable();
            $table->integer('view_time');
            $table->char('member_id',32);
            $table->string('source',12);
            $table->char('shop_id',18);
            $table->string('user_agent',1000)->nullable();
            $table->string('ip',15);

            $table->index('shop_id');
            $table->index(['shop_id','content_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('views');
    }
}
