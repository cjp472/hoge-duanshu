<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMemberGag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('member_gag', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('member_id',32)->comment('会员的id');
            $table->char('content_id',12)->comment('内容id');
            $table->string('content_type',32)->comment('内容类型:图文等,全局使用global');
            $table->integer('is_gag')->default(1)->comment('是否禁言');
            $table->timestamps();

            $table->index(['member_id','content_id','content_type']);
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
