<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_info', function (Blueprint $table) {
            $table->increments('id');
            $table->char('shop_id',18);
            $table->string('telephone',18)->comment('联系电话');
            $table->string('address')->comment('地址');
            $table->string('public_name')->comment('公众号名称');
            $table->text('public_indexpic')->comment('公众号二维码');
            $table->timestamps();
            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('shop_info');
    }
}
