<?php

namespace App\Http\Controllers\H5\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\H5\BaseController;

use App\Models;

class CourseStructController extends BaseController
{

  public function struct(Request $request, $courseId) {
    $course = $this->getCourse($courseId);
    $struct = $course->struct();
    return $this->output($struct);

  }

  protected function getCourse($courseId)
  {
    $course = Models\Course::where(['shop_id' => $this->shop['id'], 'hashid' => $courseId])->firstOrFail();
    return $course;
  }






}