<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddJoinMembercardToContentAndCommunity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('content', function (Blueprint $table) {
            $table->smallInteger('join_membercard')->default(1)->comment('是否适用会员卡 0-不适用 1-适用');
        });
        Schema::table('community', function (Blueprint $table) {
            $table->smallInteger('join_membercard')->default(1)->comment('是否适用会员卡 0-不适用 1-适用');
        });
        Schema::table('course', function (Blueprint $table) {
            $table->smallInteger('join_membercard')->default(1)->comment('是否适用会员卡 0-不适用 1-适用');
        });
        Schema::table('column', function (Blueprint $table) {
            $table->smallInteger('join_membercard')->default(1)->comment('是否适用会员卡 0-不适用 1-适用');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('content', function (Blueprint $table) {
            //
        });
        Schema::table('community', function (Blueprint $table) {
            //
        });
        Schema::table('cloumn', function (Blueprint $table) {
            //
        });
        Schema::table('course', function (Blueprint $table) {
            //
        });
    }
}
