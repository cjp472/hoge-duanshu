<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSourceToShopFlow extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shop_flow', function (Blueprint $table) {
            $table->char('source',10)->default('production')->comment('环境');
        });
        Schema::table('shop_score', function (Blueprint $table) {
            $table->char('source',10)->default('production')->comment('环境');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
