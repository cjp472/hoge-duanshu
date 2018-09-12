<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/29
 * Time: 15:03
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class MemberBindPromoter extends Model
{
    protected $table = 'member_bind_promoter';

    protected $fillable = ['state', 'is_del', 'invalid_timestamp'];

    const DEFAULT_WHERE = [
        'state' => 1,
    ];

    public static function bindPromoter($shop_id, $member_uid, $promoter, $promotion_uids)
    {
        $shop = Shop::where('hashid', $shop_id)->first();
        if ($shop && $shop->is_promotion && $shop->promotion_setting
            && $member_uid && $promoter) {
            $time = time();
            $where = ['shop_id' => $shop_id, 'member_id' => $member_uid];
            $instance = MemberBindPromoter::where($where)
                ->whereIn('promoter_id', $promotion_uids)
                ->where(['state' => 1, 'is_del' => 0])
                ->whereRaw('(invalid_timestamp > ' . $time . ' or invalid_timestamp=0)')
                ->first();
            if (!$instance) {
                //其他推广员绑定当前会员的记录设置为无效
                MemberBindPromoter::where(['shop_id' => $shop_id, 'member_id' => $member_uid, 'state' => 1])
                    ->where(self::DEFAULT_WHERE)
                    ->update(['state' => 0, 'invalid_timestamp' => time()]);
                //当前会员绑定的当前推广员的记录设置为删除
                MemberBindPromoter::where($where)->where('promoter_id', $promoter->promotion_id)->update(['is_del' => 1]);
                $valid_time = $shop->promotion_setting->valid_time * 86400;
                $instance = new MemberBindPromoter;
                $instance->shop_id = $shop_id;
                $instance->member_id = $member_uid;
                $instance->promoter_id = $promoter->promotion_id;
                $instance->state = 1;
                $instance->bind_timestamp = $time;
                $instance->invalid_timestamp = $valid_time == 0 ? 0 : $time + $valid_time;
                $instance->save();
            } else {
                $valid_time = $shop->promotion_setting->valid_time * 86400;
                $instance->invalid_timestamp = $valid_time == 0 ? 0 : $time + $valid_time;
                $instance->save();
            }
        }
    }
}