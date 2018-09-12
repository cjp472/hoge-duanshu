<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/26
 * Time: 上午9:43
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PromotionContent extends Model
{
    protected $table = 'promotion_content';

    protected $fillable = ['is_participate'];

    public $timestamps = false;

    protected $hidden = ['belongsContent','belongsColumn','belongsCourse','belongsMemberCard'];

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
}