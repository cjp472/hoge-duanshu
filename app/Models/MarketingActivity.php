<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingActivity extends Model
{
    protected $connection = 'mysql';

    protected $table = 'marketing_activity';
    const FIGHTGROUP = 'fight_group';
    const LIMITPURCHASE = 'limit_purchase';
    const PROMOTION = 'promotion';
    const COMMON_ACTIVITY = ["limit_purchase","fight_group"];
    const PROMOTER_ACTIVITY = ["promotion"];


    static function activiting($shop_id) {
        return parent::where(['shop_id'=>$shop_id])
                ->where(function ($query) {
                $query->where('end_time', 0)->orWhere('end_time','>',time());
            });
    }

}
