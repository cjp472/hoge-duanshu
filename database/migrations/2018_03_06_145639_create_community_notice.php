<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCommunityNotice extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('community_notice', function (Blueprint $table) {
            $table->increments('id');
            $table->char('hashid',12);
            $table->char('community_id',12)->comemnt('社群id');
            $table->char('shop_id',18)->comemnt('店铺id');
            $table->string('title',64)->comment('公告标题');
            $table->text('content')->comment('公告内容');
            $table->tinyInteger('display')->default(1)->comment('是否显示，0-否，1-是，默认1');
            $table->tinyInteger('top')->default(0)->comment('是否置顶，0-否，1-是，默认0');
            $table->index(['community_id','shop_id']);
            $table->index('hashid');
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
        Schema::dropIfExists('notice');
    }
}
