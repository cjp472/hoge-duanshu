<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/3/6
 * Time: 15:49
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class CommunityNote extends Model
{
    protected $table = 'community_note';

    public $hidden = ['member'];

    public function member(){
        return $this->hasOne('App\Models\Member','uid','create_id');
    }
}