<?php
/**
 * Created by PhpStorm.
 * User: huang an
 * Date: 2017/3/29
 * Time: 15:01
 */

namespace App\Models\Manage;


use Illuminate\Database\Eloquent\Model;

class Content extends Model
{


    protected $table = 'content';

    public $timestamps = false;

    public $hidden = ['belongsToUsers','belongsToShop','belongsToColumn','video','live'];

    public function belongsToColumn(){
        return $this->belongsTo('App\Models\Column','column_id','hashid');
    }

    public function belongsToShop()
    {
        return $this->belongsTo('App\Models\Manage\Shop','shop_id','hashid');
    }

    public function belongsToUsers()
    {
        return $this->belongsTo('App\Models\Manage\Users','create_user','id');
    }

    public function video(){
        return $this->hasOne('App\Models\Manage\Video','content_id','content_id');
    }

    public function live()
    {
        return $this->hasOne('App\Models\Manage\Live','content_id','content_id');
    }


}