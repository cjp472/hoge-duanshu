<?php

namespace App\Http\Controllers\Admin\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Admin\BaseController;

use App\Models;

class CourseStructController extends BaseController
{

  public function struct(Request $request, $courseId) {
    $title = $request->input('title');
    $is_free = $request->input('is_free');
    $is_free = in_array($is_free,['0','1']) ? intVal($is_free):null;
    $course = $this->getCourse($courseId);
    $remove_empty = $title ? true:false;
    $struct = $course->struct($title, $is_free,true, $remove_empty);
    return $this->output($struct);
  }

  protected function getCourse($courseId)
  {
    $course = Models\Course::where(['shop_id' => $this->shop['id'], 'hashid' => $courseId])->firstOrFail();
    return $course;
  }

}