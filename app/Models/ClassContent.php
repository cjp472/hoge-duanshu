<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/9/7
 * Time: 下午3:06
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ClassContent extends Model
{
    protected $table = 'class_content';

    public $timestamps = false;

    protected $hidden = ['belongChapter'];

    public function belongChapter()
    {
        return $this->belongsTo('App\Models\ChapterContent','chapter_id','id');
    }

    static function chaptersClasses($chaptersId, $search_title=null, $is_free=null) {
        $search_title_statement = '%' . $search_title . '%';
        
        $chaptersClasses = [];
        foreach ($chaptersId as $id) {
            $chaptersClasses[$id] = [];
        }

        $classes = self::whereIn('chapter_id', $chaptersId)
            ->orderBy('chapter_id')
            ->orderBy('order_id')
            ->orderBy('is_top', 'desc')
            ->orderBy('updated_at', 'desc')
            ->orderBy('created_at', 'asc')
            ;
        $search_title && $classes->where('title', 'LIKE', $search_title_statement);
        !is_null($is_free) && $is_free == 0 && $classes->where('is_free',0);
        $is_free == 1 && $classes->where('is_free', 1);

        $classes = $classes->get();

        foreach ($classes as $item) {
            $chaptersClasses[$item->chapter_id][] = $item;
        }
        return $chaptersClasses;
    }
}