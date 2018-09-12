<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/3/13
 * Time: 10:45
 */

namespace App\Http\Controllers\H5\Community;


use App\Http\Controllers\H5\BaseController;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\CommunityNote;
use App\Models\CommunityUser;
use App\Models\Member;
use App\Models\MemberGag;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Vinkla\Hashids\Facades\Hashids;

class CommunityNoteController extends BaseController
{
    /**
     * 帖子列表
     */
    public function noteLists(){
        $this->validateWithAttribute([
            'community_id' => 'required|alpha_dash|size:12',
            'is_boutique'   => 'numeric|in:0,1'
        ],[
            'community_id'  => '社群id',
            'is_boutique'   => '是否精选'
        ]);

        $count = request('count') ? : 10;
        $sql = CommunityNote::where([
            'community_id'  => request('community_id'),
            'shop_id'       => $this->shop['id'],
            'display'       => 1
        ]);
        //搜索是否精选的帖子
        if(request('is_boutique')) {
            $sql->where('style', 'boutique')
                ->orderByDesc('boutique_top')
                ->orderByDesc('boutique_top_time');
        }else {
            $sql->orderByDesc('top')
                ->orderByDesc('top_time');
        }
        $note_list = $sql->orderByDesc('created_at')
            ->paginate($count,['hashid as note_id','community_id','shop_id','title','content','comment_num','praise_num','created_at','create_id','create_name','boutique','indexpic','annex','top','is_gag','boutique_top','style']);
        $avatar = Member::where(['shop_id'=>$this->shop['id']])->pluck('avatar','uid');
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        $is_praise = 0;
        foreach ($note_list->items() as $item) {
            $item->indexpic = $item->indexpic ? unserialize($item->indexpic) : [];
            $item->annex = $item->annex ? unserialize($item->annex) : [];
            $item->top = request('is_boutique') ? intval($item->boutique_top) : intval($item->top);
            $item->comment_num = intval($item->comment_num);
            $item->praise_num = intval($item->praise_num);
            $item->is_boutique = intval($item->boutique);
            $item->is_gag = intval($item->is_gag) ? 1 : 0;
            $item->create_avatar = isset($avatar[$item->create_id]) ?  $avatar[$item->create_id]   : '';
            $item->create_time = hg_friendly_date($item->created_at->toAtomString());
            $item->is_collection = Collection::where(['content_id'=>$item->note_id,'content_type'=>'note'])->whereIn('member_id',$member_ids)->value('id') ? 1 : 0;
            $item->collection_num = Collection::where(['content_id'=>$item->note_id,'content_type'=>'note'])->count();
            //获取点赞状态
            if($member_ids){
                foreach ($member_ids as $member_id) {
                    if(Redis::sismember('note:praise:status:'.$item->note_id,$member_id)){
                        $is_praise = 1;
                    }
                }
            }
            $item->is_praise = intval($is_praise);
            $item->is_user_gag = MemberGag::where(['shop_id'=>$this->shop['id'],'content_id'=>request('community_id'),'content_type'=>'community','member_id'=>$this->member['id']])->value('is_gag')?1:0;;
        }
        return $this->output($this->listToPage($note_list));
    }

    /**
     * 帖子详情
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function noteDetail($id){

        $this->validateWithAttribute([
            'community_id'  => 'required|alpha_dash|size:12',
        ],[
            'community_id'  => '社群id'
        ]);
        $note = CommunityNote::where([
            'shop_id'       => $this->shop['id'],
            'community_id'  => request('community_id'),
            'hashid'        => $id,
            'display'       => 1
            ])->firstOrFail(['id','hashid as note_id','community_id','shop_id','title','content','indexpic','annex','praise_num','comment_num','created_at','create_id','create_name','boutique','is_gag','top','style']);
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        $is_praise = 0;

        $note->indexpic = $note->indexpic ? unserialize($note->indexpic) : [];
        $note->annex = $note->annex ? unserialize($note->annex) : [];
        $note->praise_num = intval($note->praise_num);
        $note->comment_num = intval($note->comment_num);
        $note->is_boutique = intval($note->boutique);
        $note->top = intval($note->top);
        $note->is_gag = intval($note->is_gag);
        $note->create_avatar = Member::where(['uid'=>$note->create_id])->value('avatar') ? : '';
        $note->is_collection = Collection::where(['content_id'=>$id,'content_type'=>'note'])->whereIn('member_id',$member_ids)->value('id') ? 1 : 0;
        $note->collection_num = Collection::where(['content_id'=>$id,'content_type'=>'note'])->count();
        //获取点赞状态
        if($member_ids){
            foreach ($member_ids as $member_id) {
                if(Redis::sismember('note:praise:status:'.$id,$member_id)){
                    $is_praise = 1;
                }
            }
        }
        $note->is_praise = $is_praise;
        $note->is_user_gag = MemberGag::where(['shop_id'=>$this->shop['id'],'content_id'=>request('community_id'),'content_type'=>'community','member_id'=>$this->member['id']])->value('is_gag')?1:0;;
        $note->create_time = hg_friendly_date($note->created_at->toAtomString());
        return $this->output($note);

    }

    /**
     * 发帖
     * @return \Illuminate\Http\JsonResponse
     */
    public function postNote(){

        $data = $this->validateCommunityNote();
        $communityNote = new CommunityNote();
        $communityNote->setRawAttributes($data);
        $communityNote->save();
        $hashid = Hashids::encode($communityNote->id);
        $communityNote->hashid = $hashid;
        $communityNote->save();
        return $this->output($communityNote);
    }

    private function validateCommunityNote(){


        $this->validateWithAttribute([
            'title'=>'required|max:32',
//            'content'=> 'required',
            'community_id'=> 'required',
        ],[
            'title' => '名称',
            'content' => '内容',
            'community_id' => '社群id',
        ]);

        //该会员是否禁言
        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        $user_gag = MemberGag::where(['shop_id'=>$this->shop['id'],'content_id'=>request('community_id'),'content_type'=>'community'])->whereIn('member_id',$member_id)->first();
        if($user_gag && $user_gag->is_gag){
            return $this->error('user-gag');
        }

        $data = [
            'shop_id' => $this->shop['id'],
            'community_id' => request('community_id'),
            'title' => request('title'),
            'content' => trim(request('content')),
            'indexpic' => request('indexpic')?serialize(request('indexpic')):'',
            'annex' => request('annex')?serialize(request('annex')):'',
            'annex_num'=> request('annex')?count(request('annex')):0,
            'boutique' => request('boutique')?:0,
            'style' => request('style')?:'',
            'display' => 1,
        ];
        request('boutique') == 1 && $data['style'] = 'boutique';
        $data['create_id'] = $this->member['id'];
        $data['create_name'] = $this->member['nick_name'];
        return $data;
    }

    /**
     * 收藏帖子
     */
    public function noteCollection(){

        $this->validateWithAttribute([
            'id'    => 'required|alpha_dash|size:12',
            'community_id'  => 'required|alpha_dash|size:12',
        ],[
            'id'    => '帖子id',
            'community_id'  => '社群id',
        ]);
        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        CommunityNote::where([
            'shop_id'=>$this->shop['id'],
            'hashid'=>request('id'),
            'community_id'=>request('community_id')
        ])->firstOrFail();
        $is_collection = Collection::where(['content_id'=>request('id'),'content_type'=>'note'])->whereIn('member_id',$member_id)->first();
        //已经收藏的取消收藏
        if($is_collection){
            $is_collection->delete();
            return $this->output([
                'success'   => 1,
                'status'    => 0
            ]);
        }else {
            $param = [
                'content_id' => request('id'),
                'content_type' => 'note',
                'member_id' => $this->member['id'],
                'shop_id' => $this->shop['id'],
                'collection_time' => hg_format_date()
            ];
            $collection = new Collection();
            $collection->setRawAttributes($param);
            $collection->save();
            return $this->output([
                'success'   => 1,
                'status'    => 1
            ]);
        }

    }

    /**
     * 帖子点赞
     */
    public function notePraise(){
        $this->validateWithAttribute([
            'id'            => 'required|alpha_dash|size:12',
            'community_id'  => 'required|alpha_dash|size:12',
        ],[
            'id'            => '帖子id',
            'community_id'  => '社群id',
        ]);

        $note = CommunityNote::where([
            'shop_id'=>$this->shop['id'],
            'hashid'=>request('id'),
            'community_id'=>request('community_id')
        ])->firstOrFail();
        $key = 'note:praise:status:'.request('id');
        $member_id =hg_is_same_member($this->member['id'],$this->shop['id']);
        foreach ($member_id as $mid){
            $ps = Redis::sismember($key,$mid);
            $praise_status = $ps ? 1 : $ps;
        }

        //如果点赞过，取消点赞
        if($praise_status){
            foreach ($member_id as $mid){
                Redis::srem($key,$mid);
            }
            if($note->praise_num > 0){
                $note->decrement('praise_num');
            }
            $note->praise_num = 0;
            $note->save();
            return $this->output([
                'success'   => 1,
                'status'    => 0
            ]);
        }else{
            Redis::sadd($key,$this->member['id']);
            $note->increment('praise_num');
            return $this->output([
                'success'   => 1,
                'status'    => 1
            ]);
        }
        return $this->output(['success'=>1]);

    }

    /**
     * 帖子禁言
     */
    public function noteGag(){
        $this->validateWithAttribute([
            'id'            => 'required|alpha_dash|size:12',
            'community_id'  => 'required|alpha_dash|size:12',
            'is_gag'        => 'required|numeric|in:0,1'
        ],[
            'id'            => '帖子id',
            'community_id'  => '社群id',
            'is_gag'        => '禁言状态'
        ]);

        $note = CommunityNote::where([
            'shop_id'=>$this->shop['id'],
            'hashid'=>request('id'),
            'community_id'=>request('community_id')
        ])->firstOrFail();

        $note->is_gag = request('is_gag');
        $note->save();
        return $this->output(['success'=>1]);
    }

    /**
     * 帖子置顶
     */
    public function noteTop(){

        $this->validateWithAttribute([
            'id'            => 'required|alpha_dash|size:12',
            'community_id'  => 'required|alpha_dash|size:12',
            'top'           => 'required|numeric|in:0,1',
            'is_boutique'   => 'numeric|in:0,1',
        ],[
            'id'            => '帖子id',
            'community_id'  => '社群id',
            'top'           => '置顶状态',
        ]);
        $communityNote = CommunityNote::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        //精选帖子置顶单独处理
        if(request('is_boutique') && $communityNote->boutique){
            $communityNote->boutique_top = request('top');
            $communityNote->boutique_top_time = request('top') ==0 ? 0 :time();
        }else {
            $communityNote->top = request('top');
            $communityNote->top_time = request('top') == 0 ? 0 : time();
        }
        $communityNote->save();
        return $this->output(['success'=>1]);
    }

    /**
     * 帖子设为精选
     */
    public function noteBoutique(){
        $this->validateWithAttribute([
            'id'            => 'required|alpha_dash|size:12',
            'community_id'  => 'required|alpha_dash|size:12',
            'boutique'      => 'required|numeric|in:0,1'
        ],[
            'id'            => '帖子id',
            'community_id'  => '社群id',
            'boutique'      => '精选状态'
        ]);
        $communityNotice = CommunityNote::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        $communityNotice->boutique = request('boutique');
        $communityNotice->style = request('boutique') ? 'boutique':'';
        $communityNotice->save();
        return $this->output(['success'=>1]);
    }

    /**
     * 帖子删除
     */
    public function noteDelete(){
        $this->validateWithAttribute([
            'id'            => 'required',
            'community_id'  => 'required'
        ],[
            'id'            => '帖子id',
            'community_id'  => '社群id'
        ]);
        $note = CommunityNote::where([
            'shop_id'       => $this->shop['id'],
            'community_id'  => request('community_id'),
            'hashid'        => request('id')
        ])->firstOrFail();
        $note->delete();
        //删除点赞状态信息
        Redis::del('note:praise:status:'.request('id'));
        //删除收藏的帖子
        Collection::where(['content_id'=>request('id'),'content_type'=>'note'])->delete();
        //删除帖子评论
        Comment::where(['content_id'=>request('id'),'content_type'=>'note'])->delete();
        return $this->output(['success'=>1]);
    }



}