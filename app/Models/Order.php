<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'order';

    public $timestamps = false;

    protected $hidden = ['belongsToContent','belongsToColumn','course','community','memberCard'];

    public function belongsToContent(){
        return $this->belongsTo('App\Models\Content','content_id','hashid');
    }

    public function belongsToColumn()
    {
        return $this->belongsTo('App\Models\Column','content_id','hashid');
    }

    public function course(){
        return $this->hasOne('App\Models\Course','hashid','content_id');
    }

    /**
     * 社群关联
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function community(){
        return $this->hasOne('App\Models\Community','hashid','content_id');
    }

    /**
     * 会员卡关联
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function memberCard(){
        return $this->hasOne('App\Models\MemberCard','hashid','content_id');
    }

    public function setExtraData($extraData) {
        return $this->extra_data = serialize($extraData);
    }
    public function getExtraData() {
        return $this->extra_data ? unserialize($this->extra_data) : [];
    }

    public function orderMemberCard($memberCard, $optionId) {
        $option = $memberCard->getOption($optionId);
        $extra_data = $this->getExtraData();
        $extra_data['membercard_option'] = $option; // 会员卡规格选项{"id":1,"value":"1年","price":1}
        $this->SetExtraData($extra_data);
    }

    static function commonContentOrder($shopid) {
        return parent::whereIn('content_type',['article','video','video', 'live'])->where('shop_id', $shopid);
    }

    static function columnContentOrder($shopid)
    {
        return parent::where('content_type', 'column')->where('shop_id', $shopid);
    }

    static function courseContentOrder($shopid)
    {
        return parent::where('content_type', 'course')->where('shop_id', $shopid);
    }

    static function membercardContentOrder($shopid)
    {
        return parent::where('content_type', 'member_card')->where('shop_id', $shopid);
    }

    static function communityContentOrder($shopid)
    {
        return parent::where('content_type', 'community')->where('shop_id', $shopid);
    }

    static function offlineCourseContentOrder($shopid)
    {
        return parent::where('content_type', 'offlinecourse')->where('shop_id', $shopid);
    }

}
