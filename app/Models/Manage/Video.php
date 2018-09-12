<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/7/20
 * Time: 下午7:58
 */
namespace App\Models\Manage;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $table = 'video';

    public $timestamps = false;

    public function videos()
    {
        return $this->belongsTo('App\Models\Manage\Videos','file_id','file_id');
    }

}