<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTypeToPromotionRecord extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promotion_record', function (Blueprint $table) {
            $table->string('promotion_type',16)->default('promotion')->comment('推广类型，promotion--推广，visit-邀请');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promotion_record', function (Blueprint $table) {
            //
        });
    }
}
