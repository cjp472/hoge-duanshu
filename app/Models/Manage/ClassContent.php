<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/9/11
 * Time: 下午3:35
 */
namespace App\Models\Manage;
use Illuminate\Database\Eloquent\Model;

class ClassContent extends Model
{
    protected $table = 'class_content';

    protected $hidden = ['belongChapter'];

    public function belongChapter()
    {
        return $this->belongsTo('App\Models\Manage\Chapter','chapter_id','id');
    }

}