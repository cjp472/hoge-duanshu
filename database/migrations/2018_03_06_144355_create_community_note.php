<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCommunityNote extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('community_note', function (Blueprint $table) {
            $table->increments('id');
            $table->char('hashid',12);
            $table->char('community_id',12)->comment('社群id');
            $table->char('shop_id',18)->comment('店铺id');
            $table->string('title',64)->comment('标题');
            $table->text('content')->comment('帖子内容');
            $table->text('indexpic')->comment('帖子图片');
            $table->text('annex')->comment('帖子附件');
            $table->integer('annex_num')->comment('附件数量');
            $table->tinyInteger('display')->default(1)->comment('显示/隐藏（1显示0隐藏））');
            $table->string('style',32)->comment('帖子类型（无-null,公告-notice,精选-boutique）');
            $table->tinyInteger('top')->default(0)->comment('是否置顶（0-否，1-是）');
            $table->tinyInteger('boutique_top')->default(0)->comment('精选是否置顶（0-否，1-是）');
            $table->string('create_id',64)->comment('创建人id');
            $table->string('create_name',64)->comment('创建人名');
            $table->tinyInteger('boutique')->default(0)->comment('是否精选，0-否，1-是');
            $table->integer('praise_num')->default(0)->comment('帖子点赞数');
            $table->integer('comment_num')->default(0)->comment('帖子评论数');
            $table->tinyInteger('is_gag')->default(0)->comment('帖子是否禁言,0-否，1-是，默认0');
            $table->integer('top_time')->default(0)->comment('置顶时间');
            $table->integer('boutique_top_time')->default(0)->comment('精选帖置顶时间');

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
        Schema::dropIfExists('note');
    }
}
