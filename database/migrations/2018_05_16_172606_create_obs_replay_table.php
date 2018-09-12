<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateObsReplayTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('obs_reply', function (Blueprint $table) {

            $table->increments('id');
            $table->char('obs_flow_uuid',64)->comment('obs直播流标识，对应直播表的obs_flow_uuid');
            $table->string('stream_id',256)->comment('流名id');
            $table->string('reply_url',1000)->comment('回看地址');
            $table->integer('duration')->comment('视频时长');
            $table->string('file_size',32)->comment('视频大小');
            $table->text('extra')->comment('冗余数据');

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
    }
}
