<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPromoterCommissionInviteCommissionToPromotionRecord extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promotion_record',function(Blueprint $table){
            $table->decimal('promoter_commission',10,2)->comment('推广佣金金额');
            $table->decimal('invite_commission',10,2)->comment('邀请佣金金额');
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
