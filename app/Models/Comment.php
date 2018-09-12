<?php

/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/7
 * Time: 15:01
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class Comment extends Model
{


    protected $table = 'comment';

    public $timestamps = false;

    protected $hidden = ['belongsToMember', 'belongsToContent', 'hasOneReply', 'belongsToCommunityNote'];

    const STATUS = [0=>'隐藏', 1=>'显示',2=>'未审核'];

    static function verboseStatus($status)
    {
        return array_key_exists($status, self::STATUS) ? self::STATUS[$status] : $status;
    }


    public function belongsToContent()
    {
        return $this->belongsTo('App\Models\Content', 'content_id', 'hashid');
    }

    public function belongsToMember()
    {
        return $this->belongsTo('App\Models\Member', 'member_id', 'uid');
    }

    public function hasOneReply()
    {
        return $this->hasOne('App\Models\Reply', 'comment_id', 'id');
    }

    public function hasOneFidReply()
    {
        return $this->hasOne('App\Models\Reply', 'comment_id', 'fid');
    }

    /**
     * 社群的帖子
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsToCommunityNote()
    {
        return $this->belongsTo('App\Models\CommunityNote', 'content_id', 'hashid');
    }

    static function memberIsCommented($member,$contentType,$contentId){
        $membersId = hg_is_same_member($member->uid, $member->shop_id);
        $num = self::where(['content_type'=>$contentType,'content_id'=>$contentId])->whereIn('member_id', $membersId)->count();
        return boolVal($num);
    }


    static function star($contentType, $contentId,$memberUid=null)
    {
        $starKeys = [1,2,3,4,5];
        $base = self::where(['content_type' => $contentType, 'content_id' => $contentId, 'status' => 1])->where('member_id','!=','-1')->whereNotNull('star');
        if(!is_null($memberUid)){
            $base->orWhere(function($query)use($contentType,$contentId,$memberUid){
                $query->where(['content_type' => $contentType, 'content_id' => $contentId, 'status' => 2,'member_id'=>$memberUid])->whereNotNull('star');
            });
        }
        $staredNumsql = clone $base;
        $staredNum = $staredNumsql->count();
        $totalStarSql = clone $base;
        $totalStar = $totalStarSql->sum('star');
        $starSql = clone $base;
        $starCollect = $starSql
            ->groupBy('star')
            ->orderBy('star')
            ->select(DB::raw('count(*) as num'), 'star')
            ->get()
            ;
        
        $starMap = $starCollect->groupBy('star')->map(function($item,$key){return $item[0];});
        foreach ($starKeys as $item) {
            if(!$starMap->has($item)){
                $starMap->put($item,['num'=>0,'star'=>$item]);
            }
        }
        $star = $starMap->sortByDesc('star')->values();
        return [
            "stared_num" => $staredNum,
            'total_star' => floatVal($totalStar),
            'avg_star' => number_format(round($staredNum ? $totalStar / $staredNum:5,1),1),
            'stars' => $star
        ];

    }


    static function CourseExport($contentType, $contentId){
        $list = self::where('comment.content_type',$contentType)
            ->where('comment.content_id',$contentId)
            ->where('comment.fid',0)
            ->where(function($query){
                $query->where('admin_reply.member_id','-1')->orWhereNull('admin_reply.member_id');
            })
            ->join('member','member.uid','=','comment.member_id')
            ->leftJoin('comment as admin_reply', 'admin_reply.fid','=','comment.id')
            ->select('comment.*', 'admin_reply.id as r_id', 'admin_reply.member_id as r_member_id', 'admin_reply.content as r_content', 'admin_reply.comment_time as r_comment_time','member.avatar','member.nick_name','member.source')
            ;
        return $list;
        
    }





}