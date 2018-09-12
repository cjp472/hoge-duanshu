<?php

/**
 * 评论
 */
namespace App\Http\Controllers\Admin\Comment;


use Illuminate\Validation\Rule;

use App\Events\AppEvent\AppCommentAddEvent;
use App\Events\CommentEvent;
use App\Events\InteractNotifyEvent;
use App\Models\Alive;
use App\Models\AliveMessage;
use App\Models\Comment;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Content;
use App\Models\Notice;
use App\Models\Reply;

class CommentController extends BaseController
{
    /**
     * 评论列表 或者 某条内容的评论
     * @return mixed
     */
    public function lists()
    {
        $this->validateWithAttribute(
            [   'praise'     => 'alpha',
                'time'       => 'alpha',
                'content_id' => 'alpha_dash|max:64',
                'content_type' => 'alpha_dash',
                'count'      => 'numeric',
                'source'     => 'alpha_dash|max:32',
                'status'     => 'numeric|in:0,1,2',
                'choice'     => 'numeric|in:0,1',
                'title'      => 'string',
                'content'    => 'string',
                'nick_name'  => 'string',
                'start_date' => 'date',
                'end_date'   => 'date'],
            ['praise'=>'点赞','time'=>'排序方式','content_id'=>'内容id','count'=>'每页条数','source'=>'来源','status'=>'显示状态','choice'=>'精选状态','title'=>'资源名称','content'=>'内容','nick_name'=>'昵称','start_date'=>'评论开始时间','end_date'=>'评论结束时间']
            );
        $is_live = $this->checkIsLive(request('content_id'));
        if($is_live){
            $data = $this->processLiveComment(request('content_id'));
        }else{
            $order = request('praise') ? 'praise' : 'comment_time';
            $desc = request('praise') ?  : (request('time')? : 'desc') ;
            $comment = Comment::where(['comment.shop_id' => $this->shop['id']])->where('comment.member_id', '<>', -1);
            request('content_id') && $comment->where(['comment.content_id' => request('content_id')]);  //内容列表查看评论，content_id存在时
            request('source') && $comment->where('member.source', '=', request('source'));
            if (request('status') !== null) {
                $comment->where('comment.status', '=', request('status'));
            };
            if (request('choice') !== null) {
                $comment->where('comment.choice', '=', request('choice'));
            };

            if(request('content_type')){
                $comment->where(['comment.content_type' => request('content_type')]);
            }else{
                $comment->whereIn('comment.content_type',['article','audio','video','note']);
            }
            
            request('title') && $comment->where('content.title', 'like', '%' . request('title') . '%');
            request('content') && $comment->where('comment.content', 'like', '%' . request('content') . '%');
            request('nick_name') && $comment->where('member.nick_name', 'like', '%' . request('nick_name') . '%');
            if (request('start_date') && !request('end_date')) $comment->whereBetween('comment.comment_time', [strtotime(request('start_date')), time()]);
            if (!request('start_date') && request('end_date')) $comment->whereBetween('comment.comment_time', [0, strtotime(request('end_date'))]);
            if (request('start_date') && request('end_date')) $comment->whereBetween('comment.comment_time', [strtotime(request('start_date')), strtotime(request('end_date'))]);
            $count = request('count') ?: 10;

            $result = $comment
                ->leftJoin('comment as t2', 'comment.fid', '=', 't2.id')//查询父子关系的评论
                ->leftJoin('member', 'comment.member_id', '=', 'member.uid')//为了能够搜索nick_name
                ->leftJoin('content', 'comment.content_id', '=', 'content.hashid')//为了能够搜索title
                ->orderBy($order, $desc)
                ->select('comment.content_id', 'comment.content_type as type', 'comment.status', 'comment.choice', 'comment.comment_time', 'comment.praise', 'comment.member_id', 'comment.content', 'comment.id', 't2.content as pcontent', 'comment.star', 'member.source')
                ->paginate($count);
            $data = $this->listToPage($result);
            foreach ($data['data'] as $item) {  //关联模型
                $item->comment_time = $item->comment_time ? date('Y-m-d H:i:s', $item->comment_time) : '';
                $item->pcontent = $item->pcontent ? $item->pcontent : '';
                $item->avatar = $item->belongsToMember ? ($item->belongsToMember->avatar ?: '') : '';
                $item->nick_name = $item->belongsToMember ? $item->belongsToMember->nick_name : '';
                if($item->type == 'note'){
                    $item->title = $item->belongsToCommunityNote ? $item->belongsToCommunityNote->title : '';
                }else {
                    $item->title = $item->belongsToContent ? $item->belongsToContent->title : '';
                }
                $item->reply = $item->hasOneReply ? $item->hasOneReply->reply : '';
                $item->reply_name = $item->hasOneReply ? $item->hasOneReply->reply_name : '';
                $item->reply_time = $item->hasOneReply ? ($item->hasOneReply->reply_time ? date('Y-m-d H:i:s', $item->hasOneReply->reply_time) : '') : '';
            }
        }
        return $this->output($data);
    }

    private function checkIsLive($content_id){
        return Alive::where(['content_id'=>$content_id])->first() ? 1 : 0;
    }

    private function processLiveComment($content_id){
        $desc = request('time')? : 'desc' ;
        $messages = AliveMessage::where(['shop_id'=>$this->shop['id'],'content_id'=>$content_id,'type'=>1])
            ->select('id','content_id','shop_id','message','member_id','time')
            ->orderBy('time',$desc)
            ->paginate(request('count') ?: 10);
        $data = $this->listToPage($messages);
        foreach ($data['data'] as $item) {
            $item->comment_time = $item->time ? date('Y-m-d H:i:s', $item->time) : '';
            $item->content = $item->message?:'';
            $item->pcontent = '';
            $item->avatar = $item->member ? ($item->member->avatar ?: '') : '';
            $item->nick_name = $item->member ? $item->member->nick_name : '';
            $item->title = Content::where(['shop_id'=>$this->shop['id'],'hashid'=>$item->content_id])->value('title')?:'';
            $item->type =  'live';
            $item->reply =  '';
            $item->praise =  0;
            $item->status =  $item->is_del?0:1;
            $item->reply_name = '';
            $item->reply_time = '';
        }
        return $data;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 直播评论状态切换
     */
    public function changeStatus(){
        $this->validateWithAttribute(['content_id'=>'required','id'=>'required','status'=>'required'],[
            'content_id'=>'直播id','id'=>'评论id','status'=>'显示隐藏状态'
        ]);
        $where = [
            'shop_id' => $this->shop['id'],
            'content_id' => request('content_id'),
            'id' => request('id'),
        ];
        AliveMessage::where($where)->update(['is_del'=>request('status')==1?0:1]);
        return $this->output(['success'=>1]);
    }


    /**
     * 系统回复某个用户的回复列表
     */
    public function replyList()
    {
        $this->validateWithAttribute(
            ['count'=>'numeric'],['count'=>'每页条数']
        );
        $count = request('count') ? : 10;
        $result = Comment::where('member_id',$this->member['id'])
            ->join('reply','comment.id','=','reply.comment_id')
            ->select('reply.reply_name','reply.reply','reply.reply_time')
            ->paginate($count);
        $data = $this->listToPage($result);
        foreach ($data['data'] as $item) {
            $item->reply_time = $item->reply_time ? date('Y-m-d H:i:s',$item->reply_time) : '';
        }
        return $this->output($data);
    }

    /**
     * 设置状态（显示隐藏）
     */
    public function changeType()
    {
        $this->validateWithAttribute(
            ['id' => 'required','status' => ['required', Rule::in([0, 1, 3])]],['id'=>'评论id','status'=>'显示隐藏状态']
        );
        $params = explode(',',request('id'));
        Comment::whereIn('id',$params)->update(['status'=>request('status')]);
        return $this->output(['success' => 1]);
    }

    /**
     * 设置精选状态
     */
    public function changeChoice(){
        $this->validateWithAttribute(
            ['id' => 'required|numeric','choice' => 'required|numeric'],['id'=>'评论id','choice'=>'精选状态']
        );
        $result = Comment::where('id',request('id'))
            ->update(['choice'=>request('choice')]);
        if($result){
            return $this->output(['success' => 1]);
        }else{
            $this->error('change-fail');
        }
    }



    /**
     * 管理员进行回复
     */
    public function adminReply(){
        $this->validateWithAttribute(
            [
                'comment_id'       => 'required|numeric',
                'reply_name'       => 'required|string',
                'reply'            =>  'required|string',
                'recipients'       => 'required|alpha_dash',
                'recipients_name'  => 'required|string',
            ],[
                'comment_id' => '评论id',
                'reply_name' => '回复昵称',
                'reply'     => '回复内容',
                'recipients' => '接收人id',
                'recipients_name' => '接收人名称',
            ]
        );
        $dataReply = $this->replyData();
        $is_exist = Reply::where('comment_id',request('comment_id'))->first();
        if($is_exist){ //管理员已经回复过了
            $this->error('reply-exist');
        }
//        $dataNotify = $this->notifyData();
        $commentData = $this->commentData();  //h5端新需求，将管理员回复数据显示在h5端
        $comment_id = Comment::insertGetId($commentData);
        $resultReply = Reply::insert($dataReply);  //数据插入reply表
        $manager = $this->interactEventData();
        event(new InteractNotifyEvent($commentData,$manager,request('recipients'),'reply',request('reply'))); //增加互动通知记录和数量
        event(new CommentEvent($commentData['content_id'],$commentData['content_type'],$this->shop['id']));   //内容的评论总数事件
        event(new AppCommentAddEvent($this->shop['id'],$comment_id,$commentData['content_id'],  $commentData['content'], -1, $commentData['fid'])); //单个评论内容同步到app
//        $resultNotify = Notice::insert($dataNotify);  //数据插入notify表
        if($resultReply ){
            return $this->output(['success' => 1]);
        }else{
            $this->error('reply-fail');
        }
    }

    public function replyDelete() {
        $this->validateWithAttribute(
            [
                'comment_id' => 'required|numeric',
            ],
            [
                'comment_id' => '评论id',
            ]
        );
        Reply::where('comment_id', request('comment_id'))->delete();
        Comment::where('fid',request('comment_id'))->where('member_id','-1')->delete();
        return $this->output(['success' => 1]);
    }

    private function commentData(){
        $comment = Comment::where('id',request('comment_id'))->select('content_id','content_type')->firstOrFail();
        $data = [
            'shop_id'      => $this->shop['id'],
            'fid'          => request('comment_id'),
            'content_id'   => $comment->content_id,
            'content_type' => $comment->content_type,
            'member_id'    => -1,
            'content'      => request('reply'),
            'comment_time' => time(),
        ];
        return $data;
    }


    public function delete(){
        $this->validateWithAttribute(
            [
                'id' => 'required|array',
            ],
            [
                'id' => '评论id',
            ]
        );
        Comment::whereIn('id',request('id'))->where('shop_id',$this->shop['id'])->delete();
        Comment::whereIn('fid', request('id'))->where('shop_id', $this->shop['id'])->delete();
        return $this->output(['success' => 1]);
    }

    /*
     * 互动通知事件  管理员信息
     */
    private function interactEventData(){
        return [
            'id'        => -1,
            'nick_name' => request('reply_name'),
            'avatar'    => config('define.default_avatar')
        ];
    }

    /**
     * 管理员回复参数整理
     */
    private function replyData(){
        return [
            'comment_id'  => request('comment_id'),
            'reply_name'  => request('reply_name'),
            'reply'       => request('reply'),
            'reply_time'  => time(),
        ];
    }

    /**
     * notify参数整理
     */
    private function notifyData(){
        return [
            'shop_id'           => $this->shop['id'],
            'sender'            => 0,//$this->user['id'],
            'sender_name'       => 0,//$this->user['name'],
            'recipients'        => request('recipients'),
            'recipients_name'   => request('recipients_name'),
            'content'           => request('reply'),
            'send_time'         => time(),
            'type'              => 0,
        ];
    }


    /**
     * 关于某个用户的评论
     */
    public function userComments()
    {
         $this->validateWithAttribute(
            ['count'=>'numeric','member_id'=>'required|alpha_dash'],['count'=>'每页条数','member_id'=>'会员id']
        );
         $count = request('count') ? : 10;
        //内容列表查看评论
        $result = Comment::where('comment.member_id',request('member_id'))
            ->leftJoin('comment as t2','comment.fid','=','t2.id')
            ->orderBy('comment_time','desc')
            ->select('comment.id','comment.content_id','comment.content_type as type','comment.content','t2.content as pcontent','comment.comment_time')->paginate($count);
        $data = $this->listToPage($result);
        foreach ($data['data'] as $item){
            $item->comment_time = $item->comment_time ? date('Y-m-d H:i:s',$item->comment_time) : '';
            $item->pcontent = $item->pcontent ? $item->pcontent : '';
            if($item->type == 'note'){
                $item->title = $item->belongsToCommunityNote ? $item->belongsToCommunityNote->title : '';
            }else {
                $item->title = $item->belongsToContent ? $item->belongsToContent->title : '';
            }
        }
        return $this->output($data);
    }

}