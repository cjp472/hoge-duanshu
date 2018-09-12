<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/25
 * Time: 下午1:59
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PromotionRecord extends Model
{
    protected $table = 'promotion_record';

    public $timestamps = false;

    protected $hidden = ['belongMember','belongsContent','belongsCourse','order'];

    public function belongMember()
    {
        return $this->belongsTo('App\Models\Member','promotion_id','uid');
    }
    public function belongsContent()
    {
        return $this->hasOne('App\Models\Content','hashid','content_id');
    }

    public function belongsColumn()
    {
        return $this->hasOne('App\Models\Column','hashid','content_id');
    }
    
    public function belongsCourse()
    {
        return $this->hasOne('App\Models\Course','hashid','content_id');
    }

    public function belongsMemberCard()
    {
        return $this->hasOne('App\Models\MemberCard','hashid','content_id');
    }

    public function order(){
        return $this->hasOne('App\Models\Order','order_id','order_id');
    }
}