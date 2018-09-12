<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/7
 * Time: 15:01
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{


    protected $table = 'invite_code';

    public function setExtraData($extraData) {
        return $this->extra_data = serialize($extraData);
    }
    public function getExtraData() {
        return $this->extra_data ? unserialize($this->extra_data) : [];
    }

    public function presentMemberCard($memberCard, $optionId) { // 赠送会员卡
        $option = $memberCard->getOption($optionId);
        $extra_data = $this->getExtraData();
        $extra_data['membercard_option'] = $option; // 会员卡规格选项{"id":1,"value":"1年","price":1}
        $this->SetExtraData($extra_data);
    }

    public function belongsMemberCard()
    {
        return $this->hasOne('App\Models\MemberCard','hashid','content_id');
    }

}