<?php
namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;

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
}