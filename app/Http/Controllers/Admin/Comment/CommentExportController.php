<?php

/**
 * 评论
 */
namespace App\Http\Controllers\Admin\Comment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;


use App\Http\Controllers\Admin\BaseController;

use App\Models;


class CommentExportController extends BaseController
{ 


  public function export(Request $request) {
    $this->validateWithAttribute([
      'content_type' => 'required|alpha_dash',
      'content_id' => 'required|alpha_dash'
    ], [
      'content_type' => '内容类型',
      'content_id' => '内容id'
    ]);
    
    $comments = Models\Comment::CourseExport($request->input('content_type'), $request->input('content_id'));
    $comments->where('comment.shop_id',$this->shop['id']);
    $request->input('nick_name') && $comments->where('member.nick_name','LIKE','%'. $request->input('nick_name').'%');
    $request->input('status') && $comments->where('comment.status', $request->input('status'));
    $comments->orderBy('comment.comment_time','desc');
    $commentCollect = $comments->get();


    $fields = [];
    $fields[] = ['头像','昵称','评分','评论内容','评论时间', '来源','状态', '管理员回复','回复时间'];

    foreach ($commentCollect as $i) {
      $fields[]=[
        $i->avatar,
        hg_emoji_encode($i->nick_name),
        number_format($i->star,0),
        hg_emoji_encode($i->content),
        date('Y-m-d H:i:s',$i->comment_time),
        Models\Member::verboseSource($i->source),
        Models\Comment::verboseStatus($i->status),
        hg_emoji_encode($i->r_id ? $i->r_content:'未回复'),
        $i->r_id ? date('Y-m-d H:i:s', $i->r_comment_time):''
      ];
    }

    Excel::create('课程评价', function ($excel) use ($fields) {
      $excel->sheet('报表', function ($sheet) use ($fields) {
        $sheet->fromArray($fields, null, 'A2', false, false);
      });
    })->export('xls');
  }

}