<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTimeToMarketingActivityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('marketing_activity', function (Blueprint $table) {
            $table->integer('start_time')->comment('活动开始时间');
            $table->integer('end_time')->comment('活动结束时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marketing_activity', function (Blueprint $table) {
            //
        });
    }
}
