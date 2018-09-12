<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQcloudTypeToFlow extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shop_flow', function (Blueprint $table) {
            $table->char('qcloud_type',10)->comment('详细类型');
            $table->timestampsTz();
        });
        Schema::table('shop_score', function (Blueprint $table) {
            $table->float('last_score',10,2)->comment('剩余短书币数量');
            $table->timestampsTz();
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
