<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateMemberCard extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('member_card',  function (Blueprint $table) {
            $table->integer('top')->comment('是否置顶');
            $table->integer('order_id')->comment('排序id');
            $table->integer('is_del')->comment('是否删除（1是0否）');
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
