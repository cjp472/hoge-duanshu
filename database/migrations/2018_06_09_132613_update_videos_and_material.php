<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateVideosAndMaterial extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('material', function (Blueprint $table) {
            $table->tinyInteger('is_del')->default(0)->comment('是否删除');
        });
        Schema::table('videos', function (Blueprint $table) {
            $table->tinyInteger('used')->default(0)->comment('是否被使用');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('material', function (Blueprint $table) {
            $table->dropColumn('is_del');
        });
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('used');
        });
    }
}
