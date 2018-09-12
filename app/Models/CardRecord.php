<?php
/**
 * Created by PhpStorm.
 * User: huang an
 * Date: 2017/3/29
 * Time: 15:03
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use App\Models\MemberCard;

class CardRecord extends Model
{
    protected $table = 'card_record';

    public $timestamps = false;

    public function member(){
        return $this->hasOne('App\Models\Member','uid','member_id');
    }

    public function memberCard(){
        return $this->hasOne('App\Models\MemberCard','hashid','card_id');
    }

    public function order(){
        return $this->hasOne('App\Models\Order','order_id','order_id');
    }

    public function optionAndExpire() {
        if($this->option){
                    $option = unserialize($this->option);
                    $this->option = $option ? $option : (object)[];
                    $this->expire = array_key_exists($option['value'], MemberCard::SPECOPTIONS)? MemberCard::SPECOPTIONS[$option['value']] : 0;
                } else {
                    $relatedOrder =  $this->order_id != '' ? $this->order : null;
                    $extraData = $relatedOrder ? $relatedOrder->getExtraData() : [];
                    if (array_key_exists('membercard_option', $extraData)) {
                        $option =  $extraData['membercard_option'];
                        $this->option = $option ? $option : (object)[];
                        $this->expire = MemberCard::SPECOPTIONS[$option['value']];
                    } else { // 旧数据
                        $this->expire = intval(($this->end_time - $this->start_time) / (60 * 60 * 24 * 30));
                        $this->option = ['value'=>$this->expire.'个月', 'price'=>$this->price, 'id'=>0];
                    }
        }
    }
}