<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopObsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_obs', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18);
            $table->string('stream_id',256)->comment('流名id');
            $table->string('push_url',1000)->comment('推流地址');
            $table->integer('expire_time')->comment('过期时间');
            $table->string('play_url',1000)->comment('播放地址');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shop_obs');
    }
}
