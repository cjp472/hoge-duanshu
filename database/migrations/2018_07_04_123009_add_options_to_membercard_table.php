<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOptionsToMembercardTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('member_card', function (Blueprint $table) {
            $table->string('options', 500)->default('')->comment('会员卡期限规格 [{"id":1,"value":"三个月","price":"10"}]');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('member_card', function (Blueprint $table) {
            //
        });
    }
}
