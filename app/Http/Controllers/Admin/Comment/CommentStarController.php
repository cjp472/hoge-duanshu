<?php

/**
 * 评论
 */
namespace App\Http\Controllers\Admin\Comment;

use Illuminate\Http\Request;
use App\Models\Comment;

class CommentStarController extends BaseController
{ 



  public function star(Request $request) {
    $this->validateWithAttribute([
      'content_type'=> 'required|alpha_dash',
      'content_id'=> 'required|alpha_dash'
    ],[
      'content_type'=>'内容类型',
      'content_id' => '内容id'
    ]);
    
    $contentType = $request->input('content_type');
    $contentId = $request->input('content_id');
    $s = Comment::star($contentType,$contentId);
    return $this->output($s);
  }
}