<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePartnerApply extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('partner_apply', function (Blueprint $table) {
            $table->increments('id');

            $table->string('company_name',64)->nullable()->comment('企业名');
            $table->string('company_email',32)->nullable()->comment('企业邮箱');
            $table->string('contacts',32)->nullable()->comment('联系人');
            $table->string('address',128)->nullable()->comment('联系地址');
            $table->string('mobile',11)->comment('手机号');
            $table->tinyInteger('state')->default(0)->comment('审核状态');
            $table->integer('apply_time')->comment('申请时间');
            $table->integer('operate_time')->comment('审核时间');
            $table->string('type',32)->default('platform')->comment();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('partner_apply');
    }
}
