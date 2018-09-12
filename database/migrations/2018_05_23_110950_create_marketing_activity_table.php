<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMarketingActivityTable extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up()
    {
        Schema::create('marketing_activity', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18);
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type')->comment('内容类型');
            $table->string('marketing_type')->comment('营销活动类型(limit_purchase,promotion等)');
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
        Schema::drop('marketing_activity');
    }
}
