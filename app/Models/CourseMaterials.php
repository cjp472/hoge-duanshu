<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseMaterials extends Model
{
    protected $table = 'course_materials';
    protected $fillable = array('class_id', 'chapter_id');
}
