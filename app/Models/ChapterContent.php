<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/9/7
 * Time: 下午4:22
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ChapterContent extends Model
{
    protected $table = 'chapter_content';

    public $hidden = ['class_hour_content'];

    public  $timestamps = false;

    public  function class_hour_content(){   //关联课时表
        return $this->hasMany('App\Models\ClassContent','chapter_id','id');
    }

    static function list($courseId) {
        return self::where('course_id',$courseId)
            ->select('id', 'title', 'is_top', 'is_default')
            ->orderBy('order_id')
            ->orderBy('is_top', 'desc')
            ->orderBy('updated_at', 'desc')
            ->orderBy('created_at', 'asc');
    }

}