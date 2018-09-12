<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/25
 * Time: 上午11:18
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $table = 'promotion';

    public $timestamps = false;

    protected $hidden = ['belongsMember','belongsVisit'];

    protected static $default_where = [
        'is_delete' => 0,
        'state' => 1,
    ];

    public function belongsMember()
    {
        return $this->belongsTo('App\Models\Member','promotion_id','uid');
    }

    public function belongsVisit()
    {
        return $this->belongsTo('App\Models\Member','visit_id','uid');
    }

}