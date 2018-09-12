<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/9/11
 * Time: 上午10:51
 */
namespace App\Models\Manage;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $table = 'course';

    public $timestamps = false;

    protected $hidden = ['belongShop','belongUser'];

    public function belongShop()
    {
        return $this->belongsTo('App\Models\Manage\Shop','shop_id','hashid');
    }

    public function belongUser()
    {
        return $this->belongsTo('App\Models\Manage\Users','create_user','id');
    }
}