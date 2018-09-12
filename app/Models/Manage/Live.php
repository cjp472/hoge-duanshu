<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/8/1
 * Time: 下午5:33
 */
namespace App\Models\Manage;
use Illuminate\Database\Eloquent\Model;

class Live extends Model
{
    protected $table = 'live';
    public $timestamps = false;

    public function videos()
    {
        return $this->belongsTo('App\Models\Manage\Videos','file_id','file_id');
    }
}