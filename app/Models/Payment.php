<?php
/**
 * 支付情况管理
 */
namespace App\Models;


use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payment';
    public $timestamps = false;
    protected $fillable = ['user_id', 'content_id', 'content_type'];


    public function course(){
        return $this->hasOne('App\Models\Course','hashid','content_id');
    }

    public function alive(){
        return $this->hasOne('App\Models\Alive','content_id','content_id');
    }

    public function column(){
        return $this->hasOne('App\Models\Column','hashid','content_id');
    }

    public function content(){
        return $this->hasOne('App\Models\Content','hashid','content_id');
    }

    static function getProductPayment($shop_id, $member_id, $content_type, $content_id){
        $member_ids = hg_is_same_member($member_id, $shop_id);
        $paymentQuery = Payment::where(['shop_id'=>$shop_id, 'content_id'=>$content_id, 'content_type'=>$content_type]);
        $paymentQuery->whereIn('user_id', $member_ids);
        $paymentQuery->where(function ($query) {
            $query->where('expire_time', 0)->orWhere('expire_time', '>', time()); // 0是永久有效
        });

        $payment = $paymentQuery->first();
        return $payment;

    }

    static function checkProductPayment($shop_id, $member_id, $content_type, $content_id){
        $payment = self::getProductPayment($shop_id, $member_id, $content_type, $content_id);
        return boolVal($payment);
    }

    /**
     * 免费订阅普通内容、课程、专栏等
     *
     * @param [type] $member
     * @param [type] $contentType
     * @param [type] $content
     * @param [type] $paymentType
     * @param [type] $expireTime
     * @return void
     */
    static function freeSubscribeContent($member,$contentType,$content,$paymentType,$expireTime){
        $filter = ['user_id'=>$member->uid,'content_type'=>$contentType,'content_id'=>$content->hashid,'shop_id'=>$member->shop_id];
        $extra = [
            'nickname'=>$member->nick_name,
            'avatar'=>$member->avatar,
            'content_title'=>$content->title,
            'content_indexpic'=>$content->indexpic,
            'order_id'=>-1,
            'price'=>0,
            'payment_type'=>$paymentType,
            'expire_time'=>$expireTime,
            'order_time'=>time()
        ];
        DB::beginTransaction();
        $lockedmember = Member::where('uid', $member->uid)->lockForUpdate()->get();
        $payment = Payment::where($filter)->first();
        if($payment){
            $payment->setRawAttributes($extra);
            $payment->save();
        }else{
            $payment = new Payment();
            $payment->setRawAttributes(array_merge($filter,$extra));
            $payment->save();
        }
        DB::commit();
        return $payment;
    }
}