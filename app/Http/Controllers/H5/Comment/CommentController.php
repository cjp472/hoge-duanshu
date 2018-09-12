<?php

/**
 * 用户评论h5端
 */
namespace App\Http\Controllers\H5\Comment;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\H5\BaseController;

use App\Events\AppEvent\AppCommentAddEvent;
use App\Events\AppEvent\AppCommentEvent;
use App\Events\CommentEvent;
use App\Events\InteractNotifyEvent;
use App\Jobs\CommentPraise;
use App\Models\Comment;
use App\Models\Course;
use App\Models\CommunityNote;
use App\Models\CommunityUser;
use App\Models\Content;
use App\Models\Member;
use App\Models\MemberGag;
use App\Models\Praise;

class CommentController extends BaseController
{

    /**
     * 列表界面 最新热门切换
     * 当参数存praise为desc时按照热门排序
     * @return mixed
     */
    public function lists()
    {
        $this->validateWithAttribute(
            [
                'content_id' => 'required|alpha_dash|max:64',
                'order' => 'string',
                'count' => 'numeric',
                'content_type' => 'alpha_dash'
            ],
            [
                'content_id' => '内容id',
                'order' => '排序参数',
                'count' => '每页条数',
                'content_type' => '内容类型'
            ]
        );
        $comment = Comment::where(['shop_id' => $this->shop['id'], 'status' => 1]);
        $order = request('order') == 'hot' ? 'praise' : 'comment_time';
        if ($order == 'praise') {  //点赞数大于等于5，是热门
            $comment->where('praise', '>=', '5');
            $comment->orderBy('praise', 'desc');
        }
        //如果传参content_type，获取指定类型内容的评论
        if (request('content_type')) {
            $comment->where('content_type', request('content_type'));
        } else {
            $comment->whereIn('content_type', ['article', 'audio', 'video']);
        }
        $comment->where('content_id', request('content_id'));
        
        if (request('content_type') == 'course') { // 课程的奇需，未审核通过时放第一位
            $orFilters = ['shop_id' => $this->shop['id'], 'member_id' => $this->member['id'], 'content_type' => request('content_type'), 'content_id' => request('content_id'), 'status' => 2]; // 2 未审核
            $comment->orWhere(function ($query) use ($orFilters) {
                $query->where($orFilters);
            });
            $comment->orderBy('status', 'desc');
        }
        $count = request('count') ? : 10;
        $result = $comment
            ->orderBy('comment_time', 'desc')
            ->select('id', 'fid', 'content_id', 'content', 'content_type as type', 'comment_time', 'choice', 'praise', 'member_id', 'star')
            ->paginate($count);
        $ids = $result->pluck('fid')->toArray();
        $contents = Comment::where(['shop_id' => $this->shop['id'], 'status' => 1])
            ->where('content_id', request('content_id'))
            ->whereIn('id', $ids)
            ->select('id', 'fid', 'content', 'praise', 'comment_time', 'member_id')
            ->get();
        foreach ($contents as $content) {
            $arr[$content['id']][] = [
                'id' => $content['id'],
                'nick_name' => $content->belongsToMember ? $content->belongsToMember->nick_name : '管理员',
                'content' => $content['content'],
                'comment_time' => $content['comment_time'] ? hg_friendly_date($content['comment_time']) : '',
            ];
        }
        $data = $this->listToPage($result);
        foreach ($data['data'] as $item) {
            $member_ids = hg_is_same_member($this->member['id'], $this->shop['id']);
            if ($member_ids) {
                $item->mine = in_array($item->member_id, $member_ids) ? 1 : 0;
                $num = [];
                foreach ($member_ids as $id) {
                    $status = Cache::get('comment:praise:status:' . $item->id . ':' . $id);
                    $status && $num[] = $status;
                }
                $praise = $num ? 1 : 0;
            } else {
                $praise = Cache::get('comment:praise:status:' . $item->id . ':' . $this->member['id']); //点赞状态
                $item->mine = $item->member_id == $this->member['id'] ? 1 : 0;           //判断评论是否是自己的，删除标志量
            }
            if (request('content_type') == 'note') {
                $community_id = CommunityNote::where(['hashid' => $item->content_id])->value('community_id');
                $item->is_user_gag = MemberGag::where(['shop_id' => $this->shop['id'], 'content_id' => $community_id, 'content_type' => 'community', 'member_id' => $item->member_id])->value('is_gag') ? 1 : 0;
            }
            $item->praise_status = $praise ? : 0;
            $item->comment_time = $item->comment_time ? hg_friendly_date($item->comment_time) : '';
            $item->reply = isset($arr[$item->fid]) ? $arr[$item->fid] : [];
            $item->member_id == -1 ? $item->avatar = config('define.default_avatar') : ($item->avatar = $item->belongsToMember ? ($item->belongsToMember->avatar ? $item->belongsToMember->avatar : '') : '');//头像
            $item->member_id == -1 ? ($item->nick_name = $item->hasOneFidReply ? $item->hasOneFidReply->reply_name : '管理员') : ($item->nick_name = $item->belongsToMember ? $item->belongsToMember->nick_name : '');//昵称
        }
        return $this->output($data);
    }

    public function simpleList()
    {
        $this->validateWithAttribute(
            [
                'content_id' => 'required|alpha_dash|max:64',
                'content_type' => 'required|alpha_dash|max:20'
            ],
            [
                'content_id' => '内容id',
                'content_type' => '内容类型'
            ]
        );

        $contentType = request('content_type');
        $contentId = request('content_id');
        $shopId = $this->shop['id'];
        $memberUid = $this->member['id'];


        $defaultAdminAvatar = config('define.default_avatar');

        $comments = Comment::join('member', 'member.uid', '=', 'comment.member_id');
        $comments->where(
            [
                'comment.shop_id' => $this->shop['id'],
                'comment.status' => 1,
                'comment.fid' => 0,
                'comment.content_type' => $contentType,
                'comment.content_id' => $contentId
            ]
        );
        $comments->when(
            $contentType == 'course',
            function ($query) use ($contentType, $contentId, $shopId, $memberUid) {
                //课程 补全未审核的 自己的评价
                $orFilters = ['comment.shop_id' => $shopId, 'comment.member_id' => $memberUid, 'comment.content_type' => $contentType, 'comment.content_id' => $contentId, 'comment.status' => 2]; // 2 未审核
                $query->orWhere(
                    function ($query) use ($orFilters) {
                        $query->where($orFilters);
                    }
                );
            }
        );

        $comments->select(
            'member.nick_name',
            'member.avatar',
            'comment.comment_time',
            'comment.content',
            'comment.member_id',
            'comment.id',
            'comment.choice',
            'comment.content_type as type',
            'comment.status',
            'comment.star'
        );

        $comments->when(
            $contentType == 'course',
            function ($query) {
                $query->orderBy('status', 'desc');
                //课程 未审核的 自己的评价放在第一位
            }
        );

        $comments->orderBy('comment_time', 'desc');

        $page = $comments->paginate(request('count', 10));

        $replyCommentsId = $page->pluck('id')->toArray();
        $replyComments = Comment::whereIn('comment.fid', $replyCommentsId)
            ->leftJoin(
                'member',
                function ($join) {
                    $join->where('comment.member_id', '!=', '-1')->on('comment.member_id', '=', 'member.uid');
                }
            )
            ->leftjoin(
                'reply',
                function ($join) {
                    $join->where('comment.member_id', '=', '-1')->on('reply.comment_id', '=', 'comment.fid');
                }
            )->select(
                'comment.id',
                'comment.member_id',
                'comment.fid',
                'comment.comment_time',
                'comment.content',
                DB::raw("CASE WHEN hg_comment.member_id = '-1' THEN hg_reply.reply_name  ELSE hg_member.nick_name END AS nick_name"),
                DB::raw("CASE WHEN hg_comment.member_id = '-1' THEN '$defaultAdminAvatar' ELSE hg_member.avatar END  AS avatar")
            )
            ->orderBy('comment_time', 'desc')
            ->get()
            ->map(
                function ($item, $key) {
                    $item->comment_time = $item->comment_time ? hg_friendly_date($item->comment_time) : '';
                    return $item;
                }
            )
            ->groupBy('fid');
        
        foreach ($page->items() as $item) {
            $item->reply = $replyComments->has($item->id) ? $replyComments[$item->id]->toArray() : [];
            $item->comment_time = $item->comment_time ? hg_friendly_date($item->comment_time) : '';
        }


        $r = $this->listToPage($page, ['status']);
        return $this->output($r);
    }

    /**
     * 根据id向上或向下查询固定数量的列表数据
     * @return mixed
     */
    public function limitLists()
    {
        $this->validateWithAttribute([
            'id' => 'numeric',
            'size' => 'numeric',
            'content_id' => 'required|alpha_dash|max:64',
            'content_type' => 'alpha_dash'
        ], [
            'id' => '评论id',
            'size' => '查询数量',
            'content_id' => '内容id',
            'content_type' => '内容类型'

        ]);
        $size = request('size') ? : 10;
        $comment = Comment::where(['shop_id' => $this->shop['id'], 'status' => 1]);
        //如果传参content_type，获取指定类型内容的评论
        if (request('content_type')) {
            $comment->where('content_type', request('content_type'));
            $counts = Comment::where(['shop_id' => $this->shop['id'], 'status' => 1, 'content_id' => request('content_id'), 'content_type' => request('content_type')])->count(); //评论总数
        } else {
            $comment->whereIn('content_type', ['article', 'audio', 'video']);
            $counts = Comment::where(['shop_id' => $this->shop['id'], 'status' => 1, 'content_id' => request('content_id')])->whereIn('content_type', ['article', 'audio', 'video'])->count(); //评论总数
        }
        request('id') && $comment->where('id', '<', request('id'));
        $order = request('order') == 'hot' ? 'praise' : 'comment_time';
        if ($order == 'praise') {  //点赞数大于等于5，是热门
            $comment->where('praise', '>=', '5');
            $comment->orderBy('praise', 'desc');
            //热门评论总数
            $counts = Comment::where(['shop_id' => $this->shop['id'], 'status' => 1, 'content_id' => request('content_id')])->where('praise', '>=', '5')->count();
        }
        $comment->where('content_id', request('content_id'));

        if (request('content_type') == 'course') { //
            $orFilters = ['shop_id' => $this->shop['id'], 'member_id' => $this->member['id'], 'content_type' => request('content_type'), 'content_id' => request('content_id'), 'status' => 2];
            $comment->orWhere(function ($query) use ($orFilters) {
                $query->where($orFilters);
            });
            $comment->orderBy('status', 'desc');
        }

        $result = $comment
            ->where('content_id', request('content_id'))
            ->orderBy('comment_time', 'desc')
            ->select('id', 'fid', 'content', 'praise', 'comment_time', 'member_id', 'content_id', 'content_type')
            ->take($size)
            ->get();

        if ($result) {
            $ids = $result->pluck('fid')->toArray();
            $aims = $this->validateComment($ids, $result);
            $data = $this->output(['total' => $counts, 'data' => $aims]);
            return $data;
        } else {
            return [];
        }
    }

    //列表公共方法
    private function validateComment($ids, $result)
    {
        $contents = Comment::where(['shop_id' => $this->shop['id'], 'status' => 1])
            ->where('content_id', request('content_id'))
            ->whereIn('id', $ids)
            ->select('id', 'fid', 'content', 'praise', 'comment_time', 'member_id', 'content_id', 'content_type')
            ->get();
        foreach ($contents as $content) {
            $arr[$content['id']][] = [
                'id' => $content['id'],
                'nick_name' => $content->belongsToMember ? $content->belongsToMember->nick_name : '',
                'content' => $content['content'],
                'comment_time' => $content['comment_time'] ? hg_friendly_date($content['comment_time']) : '',
            ];
        }
        foreach ($result as $item) {
//            $mobile = Member::where('uid',$this->member['id'])->value('mobile');
//            $member_ids = Redis::smembers('mobileBind:'.$this->shop['id'].':'.$mobile);
            $member_ids = hg_is_same_member($this->member['id'], $this->shop['id']);
            if ($member_ids) {
                $item->mine = in_array($item->member_id, $member_ids) ? 1 : 0;
                $num = [];
                foreach ($member_ids as $id) {
                    $status = Cache::get('comment:praise:status:' . $item->id . ':' . $id);
                    $status && $num[] = $status;
                }
                $praise = $num ? 1 : 0;
            } else {
                $praise = Cache::get('comment:praise:status:' . $item->id . ':' . $this->member['id']); //点赞状态
                $item->mine = $item->member_id == $this->member['id'] ? 1 : 0;           //判断评论是否是自己的，删除标志量
            }
            if (request('content_type') == 'note') {
                $community_id = CommunityNote::where(['hashid' => $item->content_id])->value('community_id');
                $item->is_user_gag = MemberGag::where(['shop_id' => $this->shop['id'], 'content_id' => $community_id, 'content_type' => 'community', 'member_id' => $item->member_id])->value('is_gag') ? 1 : 0;
            }
            $item->praise_status = $praise ? : 0;
            $item->comment_time = $item->comment_time ? hg_friendly_date($item->comment_time) : '';
            $item->reply = isset($arr[$item->fid]) ? $arr[$item->fid] : [];
            $item->member_id == -1 ? $item->avatar = config('define.default_avatar') : ($item->avatar = $item->belongsToMember ? ($item->belongsToMember->avatar ? $item->belongsToMember->avatar : '') : '');//头像
            $item->member_id == -1 ? ($item->nick_name = $item->hasOneFidReply ? $item->hasOneFidReply->reply_name : '管理员') : ($item->nick_name = $item->belongsToMember ? $item->belongsToMember->nick_name : '');//昵称
        }
        return $result;
    }



    /**
     * 用户进行评论或者回复
     * @return array
     */
    public function addComment()
    {
        $this->validateWithAttribute(
            [
                'content_id' => 'required|alpha_dash|max:64',
                'content_type' => 'required|string',
                'content' => 'required|string',
                'star' => 'numeric|min:1|max:5',
                'fid' => 'numeric'
            ],
            [
                'content_id' => '内容id',
                'content_type' => '内容类型',
                'content' => '内容',
                'fid' => '父级评论id'
            ]
        );

        $member = Member::where('uid', $this->member['id'])->first();
        $content = $this->getContentByType_(request('content_type'), request('content_id'));
        $this->checkPermission(request('content_type'), $content, $member);

        if (request('fid')) {
            $comments = Comment::find(request('fid'));
            if (!$comments) {
                $this->error('no-parent-comment');
            }
        }
        $comment = new Comment();
        $comment->shop_id = $this->shop['id'];
        $comment->content_id = request('content_id');
        $comment->content_type = request('content_type');
        $comment->content = request('content');
        $comment->member_id = $this->member['id'];
        $comment->fid = request('fid') ? : 0;
        $comment->comment_time = time();
        $comment->star = request('star', null);
        $comment->status = $comment->content_type == 'course' ? 2 : 1;//课程评论需要审核才能展示
        $comment->save();
        if ($comment) {
            event(new CommentEvent(request('content_id'), request('content_type'), request('shop_id')));
            $comment['comment_time'] = $comment['comment_time'] ? date('Y-m-d H:i:s', $comment['comment_time']) : '';
            $comment['nick_name'] = $this->member['nick_name'] ? : '';
            $comment['avatar'] = $this->member['avatar'] ? : '';
            if (request('fid')) {
                event(new InteractNotifyEvent(['shop_id' => $this->shop['id'], 'content_id' => request('content_id'), 'content_type' => request('content_type')], $this->member, $comments->member_id, 'reply', request('content')));
                $pmember_id = $comments->member_id;
                $comment['reply'] = [[
                    'content' => $comments->content,
                    'nick_name' => Member::where('uid', $pmember_id)->value('nick_name') ? : '',
                ]];
            }
            event(new AppCommentAddEvent($this->shop['id'], $comment->id, request('content_id'), request('content'), $this->member['id'], request('fid')));
            return $this->output(['data' => $comment]);
        } else {
            $this->error('comment-fail');
        }
    }

    /*
    检查评论权限
     */
    private function checkPermission($contentType, $content, $member)
    {
        switch ($contentType) {
            case 'note':
                $this->checkGag($content);
                break;
            case 'course':
                $this->checkCoursePermission($content, $member);
                break;
            default:
                break;
        }
    }

    /**
     * 判断帖子是否禁言
     */
    private function checkGag($note)
    {

        //帖子是否禁言
        if ($note->is_gag) {
            return $this->error('note-gag');
        }
        $member_ids = hg_is_same_member($this->member['id'], $this->shop['id']);
        //该会员是否禁言
        $user_gag = CommunityUser::where(['community_id' => $note->community_id, 'shop_id' => $this->shop['id']])->whereIn('member_id', $member_ids)->first();
        if (!$user_gag) {
            $this->error('no-community-user');
        }
        //该会员是否禁言
        $member_gag = MemberGag::where(['shop_id' => $this->shop['id'], 'content_id' => $note->community_id, 'content_type' => 'community'])->whereIn('member_id', $member_ids)->orderByDesc('is_gag')->first();
        if ($member_gag && $member_gag->is_gag && $user_gag->role != 'admin') {
            return $this->error('user-gag');
        }
//        if($user_gag->is_gag && $user_gag->role != 'admin'){
//            return $this->error('user-gag');
//        }
    }

    private function checkCoursePermission($course, $member)
    {
        if ($course->close_comment) {
            $this->error('close-comment');
        }

        if (!$course->isCourseStudent($member->uid)) {
            $this->error('comment-but-not-course-student');
        }
        if (Comment::memberIsCommented($member, 'course', $course->hashid)) {
            $this->error('already-comment');
        }
    }

    /**
     * 点赞操作
     * @return array
     */
    public function praise()
    {
        $this->validateWithAttribute(
            [
                'comment_id' => 'required|numeric',
            ],
            [
                'comment_id' => '评论id'
            ]
        );

        $data = $this->praiseData();
        $status = $this->judgePraise($data);
        //comment表点赞数，praise表数量增加或维护
        $job = (new CommentPraise(request('comment_id'), $this->member['id']))->onQueue(DEFAULT_QUEUE);
        dispatch($job);
        return $this->output([
            'success' => 1,
            'status' => intval($status)
        ]);
    }

    /**
     * 整理点赞数据
     * @return array
     */
    private function praiseData()
    {
        return [
            'comment_id' => request('comment_id'),
            'member_id' => $this->member['id']
        ];
    }

    /**
     * 处理点赞数据
     * @param $data
     * @return bool
     */
    private function judgePraise($data)
    {
        $is_exist = Cache::get('comment:praise:status:' . request('comment_id') . ':' . $this->member['id']);
        if ($is_exist !== null) {//存在数据则修改
            if ($is_exist == 1) { //第二次点  取消赞
                Cache::decrement('comment:praise:sum:' . request('comment_id'));
                Cache::decrement('comment:praise:status:' . request('comment_id') . ':' . $this->member['id']);
                return 0;
            } else {                          //增加赞
                Cache::increment('comment:praise:sum:' . request('comment_id'));
                Cache::increment('comment:praise:status:' . request('comment_id') . ':' . $this->member['id']);
            }
        } else {//不存在 第一次点赞增加
            $comment = Comment::findOrFail($data['comment_id']);
            Cache::increment('comment:praise:sum:' . request('comment_id'));
            Cache::forever('comment:praise:status:' . request('comment_id') . ':' . $this->member['id'], 1);
            event(new InteractNotifyEvent(['shop_id' => $this->shop['id'], 'content_id' => $comment->content_id, 'content_type' => $comment->content_type], $this->member, $comment->member_id, 'praise'));
        }
        return 1;
    }

    /**
     * 删除评论
     */
    public function deleteComment()
    {
        $this->validateWithAttribute([
            'id' => 'required|numeric',
            'content_id' => 'required|alpha_dash|size:12',
            'content_type' => 'alpha_dash'
        ], [
            'id' => '评论id',
            'content_id' => '内容id',
            'content_type' => '内容类型'
        ]);
        $member_ids = hg_is_same_member($this->member['id'], $this->shop['id']);
        switch (request('content_type')) {
            case 'note':
                if ($member_ids) {
                    $result = Comment::where(['id' => request('id'), 'content_type' => 'note'])
                        ->whereIn('member_id', $member_ids)
                        ->delete();
                } else {
                    $result = Comment::where(['id' => request('id'), 'content_type' => 'note'])
                        ->where('member_id', $this->member['id'])
                        ->delete();
                }
                if ($result) {
                    $note = CommunityNote::where('hashid', request('content_id'))->first();
                    $note->comment_num > 0 && $note->decrement('comment_num');
                    return $this->output(['success' => 1]);
                }
                break;
            default:
                if ($member_ids) {
                    $result = Comment::where('id', request('id'))
                        ->whereIn('content_type', ['article', 'audio', 'video'])
                        ->whereIn('member_id', $member_ids)
                        ->delete();
                } else {
                    $result = Comment::where('id', request('id'))
                        ->whereIn('content_type', ['article', 'audio', 'video'])
                        ->where('member_id', $this->member['id'])
                        ->delete();
                }
                if ($result) {
                    $exist_count = Content::where('hashid', request('content_id'))->first();
                    $exist_count->comment_count != 0 && $exist_count
                        ->decrement('comment_count');
                    event(new AppCommentEvent(request('id')));//app同步
                    return $this->output(['success' => 1]);
                }
                break;
        }
        $this->error('delete-fail');
    }
}
