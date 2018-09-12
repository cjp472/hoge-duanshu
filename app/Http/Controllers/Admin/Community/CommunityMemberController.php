<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2018/3/6
 * Time: 下午5:13
 */
namespace App\Http\Controllers\Admin\Community;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Collection;
use App\Models\Community;
use App\Models\CommunityNote;
use App\Models\CommunityUser;
use App\Models\MemberGag;


class CommunityMemberController extends BaseController{


    /**
     * @return \Illuminate\Http\JsonResponse
     * 社群成员列表
     */
    public function lists(){
        $this->validateWithAttribute(['community_id'=>'required'],['community_id'=>'社群id']);
        $sql = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id')]);
        request('name') && $sql->where('member_name','like','%'.request('name').'%');
        request('role') && $sql->where('role',request('role'));
        $lists = $sql->orderByDesc('top')->orderByDesc('updated_at')->paginate(request('count')?:10);
        $communityMember = $this->listToPage($lists);
        if($communityMember && $communityMember['data']){
            foreach ($communityMember['data'] as $item) {
                $item->avatar = $item->member ? $item->member->avatar : [];
                $item->is_gag = MemberGag::where(['shop_id'=>$this->shop['id'],'content_id'=>$item->community_id,'content_type'=>'community','member_id'=>$item->member_id])->value('is_gag')?1:0;
                $item->note_num = $item->note?count($item->note):0;
            }
        }
        return $this->output($communityMember);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 设置/取消管理员
     */
    public function role(){
        $this->validateWithAttribute(['member_id'=>'required','community_id'=>'required','role'=>'required'],['member_id'=>'成员id','community_id'=>'社群id','role'=>'身份状态']);
        $communityMember = CommunityUser::where(['shop_id'=>$this->shop['id'],'member_id'=>request('member_id'),'community_id'=>request('community_id')])->first();
        $admin = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id'),'role'=>'admin'])->first();
        if($admin){
            if(request('role')=='admin' && $admin->member_id != request('member_id')){
                $admin->role = 'member';
                $admin->top = 0;
            }else{
                $admin->role = 'admin';
            }
            $admin->save();
        }
        $communityMember->role = request('role');
        request('role') =='admin' && $communityMember->top = 1;
        $communityMember->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 成员禁言
     */
    public function gag(){
        $this->validateWithAttribute(['member_id'=>'required','community_id'=>'required','gag'=>'required'],['member_id'=>'成员id','community_id'=>'社群id','gag'=>'禁言状态']);
        $member_gag = MemberGag::where(['shop_id'=>$this->shop['id'],'content_id'=>request('community_id'),'content_type'=>'community','member_id'=>request('member_id')])->first();
        if($member_gag){
            $member_gag->is_gag = intval(request('gag'));
            $member_gag->save();
        }else{
            $member_gag = new MemberGag();
            $data = [
                'shop_id' => $this->shop['id'],
                'member_id' => request('member_id'),
                'content_id' => request('community_id'),
                'content_type' => 'community',
                'is_gag' => intval(request('gag')),
            ];
            $member_gag->setRawAttributes($data);
            $member_gag->save();
        }
        return $this->output(['success'=>1]);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * 成员删除（可批量））
     */
    public function delete()
    {
        $this->validateWithAttribute(['member_id'=>'required','community_id'=>'required'],['member_id'=>'成员id','community_id'=>'社群id']);
        $id = request('member_id');
        $ids = explode(',',$id);
        $communityUser = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id')])->whereIn('member_id',$ids)->get();
        if($communityUser){
            $community_note_ids = CommunityNote::where('community_id',request('community_id'))->pluck('hashid');
            foreach ($communityUser as $item){
                if($item->role == 'admin'){
                    return $this->error('admin_not_allow_delete');
                }else{
                    $item->delete();
                    Community::where(['shop_id'=>$this->shop['id'],'hashid'=>request('community_id')])->decrement('member_num');

                    if($community_note_ids) {
                        //隐藏收藏的帖子
                        Collection::whereIn('content_id', $community_note_ids)->where(['content_type'=> 'note','member_id'=>$id])->update(['display'=>0]);
                    }
                }
            }
        }
        return $this->output(['success'=>1]);
    }



}