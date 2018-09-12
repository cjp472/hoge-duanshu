<?php

namespace App\Http\Controllers\Admin\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;


use App\Http\Controllers\Admin\BaseController;

use App\Models;
use App\Http\Requests;

class CoursePreViewerController extends BaseController
{

    public function list(Request $request, $coursId){
      $count = $request->input('count', 10);

      $course = $this->getCourse($coursId);
      $preViews = $this->filter($request,$course);
      $page = $preViews->paginate($count);
      
      $output = $this->listToPage($page);
      return $this->output($output);

    }

  
  public function filter($request,$course){
    $nick_name = $request->input('nick_name', null);
    $sub = $request->input('sub', null);
    if (!is_null($sub)) {
      switch ($sub) {
        case '0':
          $sub = false;
          break;
        case '1':
          $sub = true;
          break;
        default:
          $sub = null;
          break;
      }
    }
    $source = $request->input('source', null);
    $preViews = $course->preViewers($source, $nick_name, $sub);
    return $preViews;
  }
  
  public function export(Request $request, $courseId){
    $course = $this->getCourse($courseId);
    $preViews = $this->filter($request, $course);
    $collect = $preViews->get();

    $fields = [];
    $fields[] = ['头像', '昵称', '来源', '类型', '试学次数', '最近学习时间'];

    foreach ($collect as $i) {
      $fields[] = [
        $i->avatar,
        hg_emoji_encode($i->nick_name),
        Models\Member::verboseSource($i->source),
        $i->subscribed == 1 ? '已订阅':'未订阅',
        strVal($i->pre_view_num),
        $i->last_pre_view_time
      ];
    }

    Excel::create($course->title.'课程试学', function ($excel) use ($fields) {
      $excel->sheet('报表', function ($sheet) use ($fields) {
        $sheet->fromArray($fields, null, 'A2', false, false);
      });
    })->export('xls');
  }

  protected function getCourse($courseId)
  {
    $course = Models\Course::where(['shop_id' => $this->shop['id'], 'hashid' => $courseId])->firstOrFail();
    return $course;
  }




}