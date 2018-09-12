<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSourceToShop extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shop', function (Blueprint $table) {
            $table->char('channel',8)->default('desktop')->comment('注册渠道 pc/mobile');
        });
        Schema::table('cron_statistics', function (Blueprint $table) {
            $table->integer('desktop_user')->default(0)->comment('pc端注册用户');
            $table->integer('mobile_user')->default(0)->comment('移动端注册用户');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop', function (Blueprint $table) {
            $table->dropColumn(['channel']);
        });
        Schema::table('cron_statistics', function (Blueprint $table) {
            $table->dropColumn(['desktop_user', 'mobile_user']);
        });
    }
}
