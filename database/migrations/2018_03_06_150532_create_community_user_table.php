<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCommunityUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('community_user', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->char('community_id',12)->comment('社群id');
            $table->string('member_id',64)->comment('会员id');
            $table->string('member_name',64)->comment('在社群的昵称');
            $table->tinyInteger('is_gag')->default(0)->comment('成员是否禁言，0-否，1-是，默认0');
            $table->string('role',64)->default('member')->comment('成员身份，member-成员，admin-管理员，默认member');
            $table->tinyInteger('top')->default(0)->comment('置顶状态，1-置顶，0-非置顶，管理默认1，其他默认0');

            $table->index(['shop_id','community_id']);
            $table->index('member_id');

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
        Schema::dropIfExists('community_user');
    }
}
