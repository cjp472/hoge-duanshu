<?php
/**
 * Created by PhpStorm.
 * User: huang an
 * Date: 2017/3/29
 * Time: 15:01
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class ContentType extends Model
{

    protected $connection = 'mysql';

    protected $table = 'content_type';

    public $timestamps = false;

    protected $hidden = ['belongToColumn','belongToContent','belongToCourse'];

    public function belongToColumn()
    {
        return $this->hasOne('App\Models\Column','hashid','content_id');
    }

    public function belongToContent()
    {
        return $this->hasOne('App\Models\Content','hashid','content_id');
    }

    public function belongToCourse()
    {
        return $this->hasOne('App\Models\Course','hashid','content_id');
    }
}