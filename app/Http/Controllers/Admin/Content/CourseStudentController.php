<?php

namespace App\Http\Controllers\Admin\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Admin\BaseController;

use App\Models\Course;
use App\Models\Member;
use App\Models\CourseStudent;


class CourseStudentController extends BaseController
{


  public function list(Request $request, $courseId)
  {
    $course = $this->getCourse($courseId);
    $students = $this->filterStudents($request,$course);
    $students->orderBy('course_student.created_at', 'desc');
    $page = $students->paginate($request->input('count', 10));
    $this->listSerialize($page->items(),$course);
    $output = $this->listToPage($page);
    return $this->output($output);
  }

  private function listSerialize($items, $course){
    $courseProfile = $course->profile();
    foreach ($items as $i) {
      $i->study_process = $courseProfile['total_class'] ? round($i->studied_class / $courseProfile['total_class'], 1):0;
    }
  }


  public function filterStudents($request, $course)
  {
    $status = $request->input('status');
    $entrance = $request->input('entrance');


    $students = $course->students();
    $request->input('nick_name') && $students->where("member.nick_name", 'LIKE', '%' . $request->input('nick_name') . '%');
    $request->input('source') && $students->where("member.source", $request->input('source'));
    
    $students->when($status==1,function($query){
      $query->where(function($query){
        $query->whereNull('payment.expire_time')->orWhere('payment.expire_time',0)->orWhere('payment.expire_time','>',time());
      });
    });

    $students->when(!is_null($status) && $status==0,function($query){
      $query->where('payment.expire_time','!=',0)->where('payment.expire_time','<',time());
    });

    $students->when(in_array($entrance,array_keys(CourseStudent::ENTRANCE)),function($query)use($entrance){
      $query->where('course_student.entrance',$entrance);
    });
    return $students;
  }


  public function export(Request $request, $courseId)
  {
    $fields = [];
    $fields[] = ['昵称', '来源', '已学课时', '学习进度', '金额（元）', '途径', '真实姓名', '联系方式', '性别', '生日', '头像'];

    $course = $this->getCourse($courseId);
    $students = $this->filterStudents($request,$course);
    $students = $students->orderBy('course_student.created_at', 'desc')->get();
    $courseProfile = $course->profile();
    foreach ($students as $i) {
      $fields[] = [
        "1"=> hg_emoji_encode($i->nick_name),
        "2" => Member::verboseSource($i->source),
        "3" => strVal($i->studied_class),
        "4" => $courseProfile['total_class'] ? strVal(round($i->studied_class / $courseProfile['total_class'], 1) * 100) . '%' : '',
        "5" => $i->tuition,
        "6" => CourseStudent::verboseEntrance($i->entrance),
        "7" => hg_emoji_encode($i->true_name),
        "8" => $i->mobile,
        "9" => Member::verboseSex($i->sex),
        "10" => $i->birthday ? date('Y/m/d',$i->birthday):'',
        "11" => $i->avatar
      ];
    }
    Excel::create($course->title.'学员', function ($excel) use ($fields) {
      $excel->sheet('报表', function ($sheet) use ($fields) {
        $sheet->fromArray($fields, null, 'A2', false, false);
      });
    })->export('xls');

  }

  private function getCourse($courseId)
  {
    $course = Course::where(['shop_id' => $this->shop['id'], 'hashid' => $courseId])->firstOrFail();
    return $course;
  }







}