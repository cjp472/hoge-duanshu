<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTypeToShopObs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shop_obs', function (Blueprint $table) {
            $table->string('type',32)->default('obs')->comment('直播流地址类型,obs-obs直播，online-在线直播');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_obs', function (Blueprint $table) {
            //
        });
    }
}
