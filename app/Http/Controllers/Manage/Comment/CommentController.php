<?php

/**
 * 评论
 * Gh 2017-4-26
 */

namespace App\Http\Controllers\Manage\Comment;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Comment;
use App\Models\Manage\Reply;

class CommentController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 根据条件查询评论，否则查询所有
     * @return \Illuminate\Http\JsonResponse
     */
    public function comments()
    {
        $this->validateWith([
            'member_id'   => 'alpha_dash',
            'count'       => 'numeric',
            'shop_id'     => 'alpha_dash',
            'content_id'  => 'alpha_dash'
        ]);
        $count = request('count') ? : 50 ;
        $sql = Comment::select('id','content_id','content_type','fid','member_id','content','comment_time','praise','status','choice');
        request('member_id') && $sql->where('member_id', request('member_id'));
        request('shop_id') && $sql->where('shop_id',request('shop_id'));
        request('content_id') &&  $sql->where('content_id',request('content_id'));
        $comments = $sql->orderBy('comment_time','desc')->paginate($count);
        foreach ($comments as $item) {
            $item->comment_time = $item->comment_time ? hg_format_date($item->comment_time) : '';
            $item->title = $item->belongsToContent ? $item->belongsToContent->title : '';
        }
        return $this->output($this->listToPage($comments));
    }

    /**
     * 回复消息列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function replyList()
    {
        $this->validateWith([
           'comment_id'   => 'required|numeric',
            'count'       => 'numeric'
        ]);
        $count = request('count') ? : 10;
        $reply = Reply::where('comment_id',request('comment_id'))->orderBy('reply_time','desc')->paginate($count);
        if ($reply->items()) {
            foreach ($reply->items() as $item) {
                $item->reply_time = $item->reply_time ? hg_format_date($item->reply_time ) : '';
            }
        }
        return $this->output($this->listToPage($reply));
    }


    /**
     * 评论隐藏显示
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeStatus()
    {
        $this->validateWith([
            'id'        => 'required|numeric',
            'status'   => 'required|numeric|in:0,1'
        ]);
        Comment::where('id',request('id'))->update(['status'=>request('status')]);
        return $this->output(['success'=>1]);
    }
}