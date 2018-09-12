<?php

/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/8/10
 * Time: 上午8:27
 */
namespace App\Http\Controllers\App\Comment;


use App\Http\Controllers\App\InitController;
use App\Models\AppContent;
use App\Models\Comment;
use App\Models\Content;
use Illuminate\Http\Request;

class CommentController extends InitController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 同步评论新增
     * @param Request $request
     */
    public function callback(Request $request)
    {
        $this->validateWithAttribute([
            'shop_id'     => 'required|alpha_dash',
            'comment'     => 'required',
            'replyer'     => 'required',
        ],['shop_id'=>'商铺id','comment'=>'评论内容','replyer'=>'回复的内容']);
        //根据app_content_id取出内容id和type
        $content = AppContent::where('app_content_id',$request['object_pk'])->select('content_id','content_type')->first();
        if ($content) {
            //评论内容是否存在
            $content_exist = Content::where('hashid', $content->content_id)->value('hashid');
            if ($content_exist) {
                $result = $this->formatData($request, $content);
                $id = Comment::insertGetId($result);
                if ($id) {
                    return response()->json([
                        'error_code' => 0,
                        'error_message' => '',
                        'result' => ['comment_id' => $id]
                    ]);
                }
            }
        }
    }

    /**
     * 新增评论数据整理
     * @param $request
     * @param $content
     * @return array
     */
    private function formatData($request,$content)
    {
        return [
            'content'       => $request['comment'],
            'comment_time'  => strtotime($request['submit_date']),
            'member_id'     => $request['replyer']['ori_id'] ? : '',
            'fid'           => $request['reply_comment'] ? (isset($request['reply_comment']['ori_id']) ? $request['reply_comment']['ori_id'] : 0) : 0,
            'praise'        => $request['like'],
            'status'        => 1,
            'choice'        => 0,
            'shop_id'       => $request['shop_id'],
            'content_id'    => $content->content_id,
            'content_type'  => $content->content_type
        ];
    }

    /**
     * 同步评论删除
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteComment(Request $request)
    {
        $this->validateWith([
            'id'         => 'required|numeric',
            'member_id' => 'required|alpha_dash'
        ]);
        $res = Comment::where(['id'=>$request['id'],'member_id'=>$request['member_id']])->delete();
        return response()->json([
            'error_code'     => $res ? 0 : 1,
            'error_message'  => $res ? '' : 'fail-delete'
        ]);
    }




}
