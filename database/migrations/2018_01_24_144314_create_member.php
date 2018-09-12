<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMember extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('member', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uid',32)->comment('会员id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('openid',32)->nullable()->comment('微信的openid');
            $table->string('union_id',128)->nullable()->comment('微信的unionid');
            $table->string('source',32)->nullable()->comment('来源');
            $table->string('avatar',1000)->nullable()->comment('会员头像');
            $table->string('nick_name',32)->comment('会员昵称');
            $table->string('true_name',32)->nullable()->comment('真实姓名');
            $table->tinyInteger('sex')->default(0)->comment('会员性别 0未知 1男 2女');
            $table->integer('birthday')->nullable()->comment('生日');
            $table->string('mobile',15)->nullable()->comment('联系方式');
            $table->string('email',32)->nullable()->comment('邮箱');
            $table->integer('create_time')->comment('创建时间');
            $table->decimal('amount',10,2)->comment('消费金额');
            $table->string('address',256)->nullable()->comment('地址');
            $table->string('company',64)->nullable()->comment('公司');
            $table->string('position',32)->nullable()->comment('职位');
            $table->string('industry',32)->nullable()->comment('行业');
            $table->string('extra',256)->nullable()->comment('其他信息');
            $table->string('language',16)->nullable()->comment('语言');
            $table->string('ip',15)->comment('ip');
            $table->string('province',16)->nullable()->comment('省份');
            $table->tinyInteger('gag')->default(0)->comment('是否禁言');
            $table->tinyInteger('is_black')->default(0)->comment('是否黑名单');
            $table->tinyInteger('is_auth')->default(1)->comment('是否有内容查看权限');
            $table->string('password',255)->nullable()->comment('密码');

            $table->index('shop_id');
            $table->index('uid');
            $table->index(['shop_id','openid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('member');
    }
}
