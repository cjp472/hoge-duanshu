<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateM2oMemberTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_m2o_member', function (Blueprint $table) {
            $table->integer('id',true,true);
            $table->char('shop_id',18)->comment('店铺id');
            $table->integer('member_id')->comment('m2o会员id');
            $table->string('member_guid',128)->comment('m2o会员guid');
            $table->string('member_name',32)->comment('m2o会员名');
            $table->string('password',64)->comment('m2o会员密码');
            $table->text('member_extra')->comment('信息冗余');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shop_m2o_member');
    }
}
