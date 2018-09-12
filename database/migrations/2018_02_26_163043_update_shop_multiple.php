<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateShopMultiple extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shop_multiple', function (Blueprint $table) {
            $table->string('base',256)->nullable()->comment('基数');
            $table->string('multiple',256)->nullable()->comment('倍数')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_multiple', function (Blueprint $table) {
            $table->dropColumn('base');
        });
    }
}
