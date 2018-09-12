<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateVersionExpire extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('version_expire', function (Blueprint $table) {
            $table->tinyInteger('is_expire')->default(0)->comment('是否到期');
            $table->tinyInteger('method')->default(0)->comment('获取方式 0-未知 2-手动设置 1-购买');
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
        Schema::table('version_expire', function (Blueprint $table) {
            $table->dropColumn('is_expire');
            $table->dropColumn('method');
            $table->dropColumn('created_at');
            $table->dropColumn('updated_at');
        });
    }
}
