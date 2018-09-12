<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserShop extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_shop', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->char('shop_id',18);
            $table->string('permission',1000)->comment('权限');
            $table->tinyInteger('effect')->default(1)->comment('是否生效');
            $table->tinyInteger('admin')->default(0)->comment('是否管理员');
            $table->tinyInteger('host_dep')->nullable()->comment('用户所在部门');
            $table->string('sub_dep',256)->nullable()->comment('用户所在部门集合');
            $table->index(['user_id','shop_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('user_shop');
    }
}
