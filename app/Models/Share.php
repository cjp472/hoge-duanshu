<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/4/5
 * Time: 16:54
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    protected $table = 'share';
    public $timestamps = false;

    protected $fillable = ['shop_id'];

    public function shops(){
        return $this->hasOne('App\Models\Shop','hashid','shop_id');
    }

}