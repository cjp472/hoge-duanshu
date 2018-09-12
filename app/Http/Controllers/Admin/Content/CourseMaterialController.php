<?php

namespace App\Http\Controllers\Admin\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Admin\BaseController;

use App\Models\ChapterContent;
use App\Models\ClassContent;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseMaterials;

/*
课程学习资料
 */
class CourseMaterialController extends BaseController
{

  /*
    课程资料创建
   */
  public function create(Request $request, $courseId)
  {
    $request->course = $this->getCourse($courseId);
    $v = $this->validateMaterialForm($request, $courseId);
    $v['context']['course'] = $request->course;
    DB::beginTransaction();
    try {
      $c = $this->createCourseMaterial($v['form'], $v['context']);
    } catch (Exception $e) {
      DB::rollBack();
      throw $e;
    }
    DB::commit();
    $material = $c['material'];
    $material->detailSerialize();
    return $this->output($material);
  }

  public function detail(Request $request, $courseId, $materialId)
  {
    $course = $this->getCourse($courseId);
    $material = CourseMaterial::where(['course_id' => $courseId, 'id' => $materialId])->firstOrFail();
    $material->detailSerialize();
    return $this->output($material);
  }

  public function bulkDelete(Request $request, $courseId)
  {

    $this->validateWithAttribute(
      [
        'id' => 'required|array|min:1'
      ],
      [
        'id' => '课件id'
      ]
    );
    $course = $this->getCourse($courseId);
    $materialsId = CourseMaterial::where('course_id', $courseId)->whereIn('id', $request->input('id'))->pluck('id')->toArray();
    CourseMaterial::deleteMaterials($materialsId);
    CourseMaterials::whereIn('material_id', $materialsId)->delete();
    return $this->output(['success' => 1]);
  }

  public function order(Request $request, $courseId, $materialId)
  {

    $this->validateWithAttribute(
      [
        'order' => 'required|integer|min:1'
      ],
      [
        'id' => '排序'
      ]
    );
    $material = $this->getMaterial($courseId, $materialId);
    $course = $material->course;
    unset($material->course);
    DB::beginTransaction();
    try {
      $affected = $this->_order($material, $courseId, $request->input('order'));
    } catch (\Exception $e) {
      DB::rollBack();
      throw $e;
    }
    DB::commit();
    return $this->output(['success' => 1, 'affected'=>$affected]);
  }

  protected function _order($material, $courseId, $order) {
      $max = CourseMaterial::where('course_id', $courseId)->count();
      // $_order = $order < $max ? $order : $max;
      DB::statement('SET @row_number = 0;');
      $affected = DB::update("UPDATE hg_course_material 
                      INNER JOIN
                      (SELECT id, (@row_number:=@row_number + 1) AS num FROM hg_course_material WHERE course_id = ? AND id != ? ORDER BY `order` ASC, created_at DESC) AS o 
                      ON
                      hg_course_material.id = o.id
                      SET hg_course_material.`order` = o.num;", [$courseId, $material->id]);

      if ($order < $max) {
        $material->order = $order - 0.5;
      } else {
      $material->order = $max;
      }
      $material->save(['order']);
      return $affected;
  }



  public function update(Request $request, $courseId, $materialId)
  {
    $course = $this->getCourse($courseId);
    $material = CourseMaterial::where(['course_id' => $courseId, 'id' => $materialId])->firstOrFail();
    $v = $this->validateMaterialForm($request, $courseId, $materialId);
    $v['context']['course'] = $course;
    DB::beginTransaction();
    try {
      $c = $this->updateCourseMaterial($material, $v['form'], $v['context']);
    } catch (Exception $e) {
      DB::rollBack();
      throw $e;
    }
    DB::commit();
    $material = $c['material'];
    $material->detailSerialize();
    return $this->output($material);
  }

  private function createCourseMaterial($form, $context)
  {
    $m = new CourseMaterial;
    $m->title = $form['title'];
    $m->content = $form['content'];
    $m->course_id = $context['course']->hashid;
    $m->save();

    $r = new CourseMaterials;
    $r->course_id = $context['course']->hashid;
    $r->chapter_id = $context['chapter'] ? $context['chapter']->id : null;
    $r->class_id = $context['class'] ? $context['class']->id : null;
    $r->material_id = $m->id;
    $r->save();
    return ['material' => $m, 'bind' => $r];
  }


  private function updateCourseMaterial($m, $form, $context)
  {
    $m->title = $form['title'];
    $m->content = $form['content'];
    $m->course_id = $context['course']->hashid;
    $m->save();

    $chapter_id = $context['chapter'] ? $context['chapter']->id : null;
    $class_id = $context['class'] ? $context['class']->id : null;
    $r = CourseMaterials::updateOrCreate(
      ['course_id' => $context['course']->hashid, 'material_id' => $m->id],
      ['chapter_id' => $chapter_id, 'class_id' => $class_id]
    );

    return ['material' => $m, 'bind' => $r];
  }


  private function getCourse($courseId)
  {
    $course = Course::where(['shop_id' => $this->shop['id'], 'hashid' => $courseId])->firstOrFail();
    return $course;
  }

  public function getMaterial($courseId, $materialId)
  {
    $course = $this->getCourse($courseId);
    $material = CourseMaterial::where(['course_id' => $courseId, 'id' => $materialId])->firstOrFail();
    $material->course = $course;
    return $material;
  }

  public function checkBind($courseId, $chapterId, $classId, $materialId)
  {
    if (CourseMaterial::checkBind($courseId, $chapterId, $classId, $materialId)) {
      if ($chapterId && is_null($classId)) {
        $this->errorWithText('material-bind-chapter-error', '该章节已绑定资料，请选择其他章节');
      }
      if ($chapterId && $classId) {
        $this->errorWithText('material-bind-class-error', '该课时已绑定资料，请选择其他课时');
      }
    }
  }

  private function validateMaterialForm($request, $courseId, $materialId=null)
  {
    $this->validateWithAttribute(
      [
        'title' => 'required|max:100',
        'chapter_id' => 'max:64',
        'class_id' => 'max:64',
        'content' => 'required|max:300000'
      ],
      [
        'title' => '标题',
        'chapter_id' => '章节',
        'class_id' => '课时',
        'content' => '正文'
      ]
    );
    $chapter_id = $request->input('chapter_id');
    $class_id = $request->input('class_id');
    $context = [
      'chapter' => null,
      'class' => null
    ];
    if ($chapter_id) {
      $chapter = ChapterContent::where('course_id', $courseId)->where('id', $chapter_id)->firstOrFail();
      $context['chapter'] = $chapter;
    }
    if ($class_id) {
      if (!$chapter_id) {
        $this->errorWithText('chapter-not-selected', '章节未选取');
      }
      $class = ClassContent::where(['chapter_id' => $chapter_id, 'id' => $class_id])->firstOrFail();
      $context['class'] = $class;
    }

    $this->checkBind($courseId, $chapter_id, $class_id,$materialId);

    return ['form' => $request->all(), 'context' => $context];

  }

  public function list(Request $request, $courseId)
  {
    $course = $this->getCourse($courseId);
    $materials = $this->getMaterials($request, $course);
    $pageMaterials = $materials->paginate($request->input('count', 10));
    $pageMaterials->map(function($item){
      $item->uv = $item->unique_member;
      $item->pv = $item->view_count;
    });
    CourseMaterial::listSerialize($pageMaterials->items());
    $output = $this->listToPage($pageMaterials, ['content', 'chapter_id', 'class_id', 'chapter_title', 'class_title','unique_member','view_count']);
    return $this->output($output);
  }

  private function getMaterials($request, $course)
  {
    $list = CourseMaterial::lists(['course_material.course_id' => $course->hashid]);
    $title = $request->input('title');
    $title && $list->where('course_material.title','LIKE',"%$title%");
    return $list;
  }

}