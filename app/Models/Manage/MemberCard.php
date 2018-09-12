<?php
namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;

class MemberCard extends Model
{
    protected $table = 'member_card';
    public $timestamps = false;

    public function record(){
        return $this->hasMany('App\Models\Manage\CardRecord','card_id','hashid');
    }
}