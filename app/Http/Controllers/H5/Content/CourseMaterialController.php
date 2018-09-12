<?php

namespace App\Http\Controllers\H5\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\H5\BaseController;

use App\Events\CourseMaterialViewEvent;


use App\Models;


class CourseMaterialController extends BaseController
{

  public function list(Request $request, $courseId)
  {
    $course = $this->getCourse($courseId);
    $matetials = $this->getMaterials($request, $course);
    $page = $matetials->paginate($request->input('count', 10));
    Models\CourseMaterial::listSerialize($page->items());
    $output = $this->listToPage($page, ['content', 'chapter_id', 'class_id', 'chapter_title', 'class_title','view_count','unique_member']);
    return $this->output($output);

  }

  public function detail(Request $request, $courseId, $materialId)
  {
    $hiddien = ['order'];

    $course = $this->getCourse($courseId);
    $material = Models\CourseMaterial::where(['course_id' => $courseId, 'id' => $materialId])->firstOrFail();
    $material->detailSerialize();
    $material->makeHidden($hiddien);
    $this->postGetDetail($material,$course);
    return $this->output($material);
  }


  public function postGetDetail($material,$course){
    event(new CourseMaterialViewEvent($course,$material,$this->member['id']));
  }


  protected function getMaterials($request, $course)
  {
    $list = Models\CourseMaterial::lists(['course_material.course_id' => $course->hashid]);
    return $list;
  }

  protected function getCourse($courseId)
  {
    $course = Models\Course::where(['shop_id' => $this->shop['id'], 'hashid' => $courseId])->firstOrFail();
    return $course;
  }

}