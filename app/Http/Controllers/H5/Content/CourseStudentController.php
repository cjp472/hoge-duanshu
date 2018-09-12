<?php

namespace App\Http\Controllers\H5\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\H5\BaseController;

use App\Models;

class CourseStudentController extends BaseController
{

  public function list(Request $request, $courseId)
  {
    $hidden = ['mobile', 'source', 'amount', 'true_name', 'type', 'student_type', 'entrance', 'pre_views', 'birthday','tuition'];
    $course = $this->getCourse($courseId);
    $students = $course->students();
    $students->orderBy('course_student.created_at','desc');
    $page = $students->paginate($request->input('count', 10));
    $output = $this->listToPage($page, $hidden);
    return $this->output($output);
  }

  protected function getCourse($courseId)
  {
    $course = Models\Course::where(['shop_id' => $this->shop['id'], 'hashid' => $courseId])->firstOrFail();
    return $course;
  }

}