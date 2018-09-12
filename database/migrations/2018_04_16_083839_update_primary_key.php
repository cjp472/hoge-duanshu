<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePrimaryKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tables = [
            'admin_logs','applet_notice','applet_notice_user','article','audio','collection',
            'column','comment','community','community_note','community_notice','community_user',
            'content','content_remain_record','content_remain_relation','cron_statistics',
            'failed_jobs','feedback','help_center','limit_purchase','live','member','member_gag',
            'member_notify','message_record','migrations','notify','praise','public_article','reply',
            'share','shop','shop_flow','shop_score','shop_template_id','template_notify','type','users',
            'version_order','video','video_class',

        ];
        foreach ($tables as $table_name) {
            Schema::table($table_name,  function (Blueprint $table) {
                $table->integer('id',1)->change();
            });
        }



    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
}
