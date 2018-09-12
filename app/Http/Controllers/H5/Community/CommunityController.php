<?php
/**
 * Created by PhpStorm.
 * h5端社群相关接口
 * User: hoge
 * Date: 2018/3/6
 * Time: 15:40
 */

namespace App\Http\Controllers\H5\Community;


use App\Events\JoinCommunityEvent;
use App\Http\Controllers\H5\BaseController;
use App\Models\CardRecord;
use App\Models\Collection;
use App\Models\Community;
use App\Models\CommunityNote;
use App\Models\CommunityNotice;
use App\Models\CommunityUser;
use App\Models\Member;
use App\Models\MemberGag;
use Illuminate\Support\Facades\Redis;

class CommunityController extends BaseController
{


    /**
     * 社群列表
     */
    public function communityLists(){

        $count = request('count') ? : 10;
        $where = ['shop_id'=>$this->shop['id'],'display'=>1];
        onlyFreeContent() && $where['pay_type'] = 0;
        $sql = Community::where($where);
        $filters = $this->contentCommonFilters();
        $sql = $this->filterSql($sql, $filters);
        $community = $sql->orderByDesc('updated_at')
            ->paginate($count,['hashid','shop_id','title','brief','indexpic','pay_type','price','member_num','created_at','join_membercard']);
        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($community->items()){
            $shopHighestMembercard = $this->shopHighestDiscountMembercard();
            foreach ($community->items() as $item) {
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : [];
                $item->community_id = $item->hashid;
                $item->membercard_discount = $this->shopHighestDiscount($shopHighestMembercard, $item->join_membercard);
                $item->is_join = CommunityUser::where('community_id',$item->community_id)
                    ->whereIn('member_id',$member_id)
                    ->where(function ($query) {
                        $query->where('expire', 0)
                            ->orWhere('expire', '>', time());
                    })
                    ->first() ? 1 : 0;
            }
        }
        return $this->output($this->listToPage($community));

    }


    /**
     * 社群详情
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function communityDetail($id)
    {
        $community = Community::where(['shop_id'=>$this->shop['id'],'hashid'=>$id])->firstOrFail();

        $community->community_id = $community->hashid;
        $community->total_note_num = CommunityNote::where(['shop_id'=>$this->shop['id'],'community_id'=>$id,'display'=>1])->count();
        $community->boutique_note_num = CommunityNote::where(['shop_id'=>$this->shop['id'],'community_id'=>$id,'display'=>1,'boutique'=>1])->count();
        $community->indexpic = $community->indexpic ? hg_unserialize_image_link($community->indexpic) : [];
//        $community->is_join = CommunityUser::where(['community_id'=>$id,'member_id'=>$this->member['id']])->value('id') ? 1 : 0;
        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        $communityUser = CommunityUser::where(['community_id'=>$id])
            ->whereIn('member_id',$member_id)
            ->where(function ($query) {
                $query->where('expire', 0)
                    ->orWhere('expire', '>', time());
            })
            ->first();
        $community->is_join = $communityUser ? 1 : 0;
        $community->pay_type = intval($community->pay_type);
        $original_price = $community->price;
        $community->price = $this->getDiscountPrice($community->price,'','',boolVal($community->join_membercard));
        if( $original_price != $community->price) {
            $community->cost_price = $original_price;
        }
        $community->annex_num = intval(CommunityNote::where(['shop_id'=>$this->shop['id'],'community_id'=>$id,'display'=>1])->sum('annex_num'));
        $community->role = $communityUser && $communityUser->role ? $communityUser->role : 'member';
        $community->is_user_gag = MemberGag::where(['shop_id'=>$this->shop['id'],'content_id'=>$id,'content_type'=>'community'])->whereIn('member_id',$member_id)->value('is_gag')?1:0;
        return $this->output($community);
    }

    //处理内容价格
    private function processPrice($data){
        //获取小程序和h5端会员id
        $mid = hg_is_same_member($this->member['id'],$this->shop['id']);
        //取最小折扣的会员卡到期时间
        $memberCard = CardRecord::whereIn('member_id',$mid)
            ->where('shop_id',$this->shop['id'])
            ->where('end_time','>=',time())
            ->where('start_time','<',time())
            ->orderBy('discount','desc')
            ->first(['end_time']);
        return [
            'price'=>$this->getDiscountPrice($data->price,'','',boolVal($data->join_membercard)),
            'expire_time'=>$memberCard && $memberCard->end_time ? $memberCard->end_time :0,     //折扣价格到期时间
        ];
    }

    /**
     * 社群设置
     * @return \Illuminate\Http\JsonResponse
     */
    public function communitySettings(){

        $this->validateWithAttribute([
            'community_id'  => 'required|alpha_dash|size:12',
        ],[
            'community_id'  => '社群id'
        ]);

        $community = Community::where([
            'shop_id'=>$this->shop['id'],
            'hashid'    => request('community_id'),
        ])->firstOrFail(['hashid as community_id','title','indexpic','authority','member_num']);
        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        $member_role = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id')])->whereIn('member_id',$member_id)->value('role');
        if($member_role == 'member'){
            $community->makeHidden(['authority']);
        }
        $community->role = $member_role;
        $community->indexpic = $community->indexpic ? hg_unserialize_image_link($community->indexpic) : [];
        $community->total_note_num = CommunityNote::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id'),'display'=>1])->count();
        $community->member_nick_name = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id')])->whereIn('member_id',$member_id)->value('member_name');
        $community->member_num = intval($community->member_num);
        return $this->output($community);

    }

    /**
     * 修改社群设置
     */
    public function setCommunitySettings(){

        $this->validateWithAttribute([
            'community_id'  => 'required|alpha_dash|size:12',
            'nick_name'     => 'required|alpha_dash|max:64',
            'authority'     => 'alpha_dash|in:all,admin'
        ],[
            'community_id'  => '社群id',
            'nick_name'     => '社群成员昵称',
            'authority'     => '社群发帖权限',
        ]);
        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        $community = Community::where([
            'shop_id'=>$this->shop['id'],
            'hashid'    => request('community_id'),
        ])->firstOrFail();
        $community_user = CommunityUser::where([
            'shop_id'=>$this->shop['id'],
            'community_id'=>request('community_id'),
        ])
            ->whereIn('member_id',$member_id)
            ->where(function ($query) {
            $query->where('expire', 0)
                ->orWhere('expire', '>', time());
            })->first();

        if($community_user->role == 'admin'){
            request('authority') && $community->authority = request('authority');
            $community->save();
        }
        $community_user->member_name = request('nick_name');
        $community_user->save();

        return $this->output(['success'=>1]);

    }

    /**
     * 社群成员列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function communityUser(){
        $this->validateWithAttribute([
            'id'    => 'required|alpha_dash|size:12'
        ],[
            'id'    => '社群id'
        ]);

        $count = request('count') ? : 10;
        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        $member = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('id')])
            ->orderByDesc('updated_at')
            ->paginate($count,['id','shop_id','community_id','member_id','member_name','is_gag','created_at']);
        if($member->items()){
            foreach ($member->items() as $item) {
                $item->avatar = $item->member ? $item->member->avatar : '';
                $item->is_user_gag = MemberGag::where(['shop_id'=>$this->shop['id'],'content_id'=>request('id'),'content_type'=>'community'])->whereIn('member_id',$member_id)->value('is_gag')?1:0;
            }
        }
        return $this->output($this->listToPage($member));
    }

    /**
     * 社群公告列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function communityNotice(){
        $this->validateWithAttribute([
            'id'    => 'required|alpha_dash|size:12'
        ],[
            'id'    => '社群id'
        ]);
        $count = request('count') ? : 10;
        $notice = CommunityNotice::where(['shop_id'=>$this->shop['id'],'community_id'=>request('id'),'display'=>1])
            ->orderByDesc('top')
            ->orderByDesc('top_time')
            ->orderByDesc('created_at')
            ->paginate($count,['hashid as notice_id','shop_id','community_id','title','content','created_at']);
        if($notice->items()){
            foreach ($notice->items() as $item) {
                $item->create_time = hg_friendly_date($item->created_at->toAtomString());
            }
        }
        return $this->output($this->listToPage($notice));

    }

    /**
     * 我参加的社群列表
     */
    public function myCommunityList()
    {
        $count = request('count') ? : 10;
        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        $member = CommunityUser::where('shop_id',$this->shop['id'])
            ->whereIn('member_id',$member_id)
            ->where(function ($query) {
                $query->where('expire', 0)
                    ->orWhere('expire', '>', time());
            })
            ->orderByDesc('created_at')
            ->paginate($count,['shop_id','community_id']);
        if($member->items()){
            foreach ($member->items() as $item) {
                $item->title = $item->community ? $item->community->title : '';
                $item->brief = $item->community ? $item->community->brief : '';
                $item->indexpic = $item->community ? ($item->community->indexpic ? hg_unserialize_image_link($item->community->indexpic) : []) : [];
                $item->member_num = $item->community ? intval($item->community->member_num) : 0;
                $item->is_join = 1;//是否加入社群，前端方便操作
            }
        }
        return $this->output($this->listToPage($member));
    }

    /**
     * 我的收藏列表
     */
    public function myCollectionList()
    {
        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        $count = request('count') ? : 10;
        $collection = Collection::where(['shop_id'=>$this->shop['id'],'display'=>1])
            ->whereIn('member_id',$member_id)
            ->orderByDesc('collection_time')
            ->paginate($count,['shop_id','content_id','content_type']);
        if($collection->items()){
            foreach ($collection as $item) {
                switch ($item->content_type){
                    case 'note':
                        $item->is_boutique = $item->note ? intval($item->note->boutique) : 0;
                        $item->title = $item->note ? $item->note->title : '';
                        $item->content = $item->note ? $item->note->content : '';
                        $item->note_id = $item->content_id;
                        $item->praise_num = $item->note ? intval($item->note->praise_num) : 0;
                        $item->comment_num = $item->note ? intval($item->note->comment_num) : 0;
                        $item->create_time = $item->note ? hg_friendly_date($item->note->created_at->toAtomString()) : '';
                        $item->create_id = $item->note ? ($item->note->create_id ? : '') : '';
                        $item->create_name = $item->note ? ($item->note->create_name ? : '') : '';
                        $item->create_avatar = $item->create_id ? (Member::where(['uid'=>$item->create_id])->value('avatar') ? : '' ): '';
                        $item->indexpic = $item->note ? ($item->note->indexpic ? unserialize($item->note->indexpic) : []) : [];
                        $item->annex =  $item->note ? ($item->note->annex ? unserialize($item->note->annex) : []) : [];
                        $item->is_collection = Collection::where(['content_id'=>$item->content_id,'content_type'=>'note'])->whereIn('member_id',$member_id)->value('id') ? 1 : 0;
                        $item->is_praise = Redis::sismember('note:praise:status:'.$item->content_id,$this->member['id']) ? 1 : 0;
                        $item->community_id = $item->note ? $item->note->community_id : '';
                        $item->is_user_gag = MemberGag::where(['shop_id'=>$this->shop['id'],'content_id'=>$item->community_id,'content_type'=>'community','member_id'=>$item->create_id])->value('is_gag')?1:0;
                        $item->role = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>$item->community_id])->whereIn('member_id',$member_id)->value('role');
                        $item->authority = Community::where(['shop_id'=>$this->shop['id'],'hashid'=> $item->community_id,])->value('authority');
                        break;
                    default:
                        break;
                }
            }
        }

        return $this->output($this->listToPage($collection));
    }

    /**
     * 免费加入社群
     */
    public function joinCommunity(){
        $this->validateWithAttribute([
            'id'    => 'required|alpha_dash|size:12',
        ],[
            'id'    => '社群id'
        ]);
        $community = Community::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id')])->firstOrFail();
        $member = Member::where(['shop_id'=>$this->shop['id'],'uid'=>$this->member['id']])->first();

        $subPermDetail = $this->checkFreeSubscribePermission($member, $community->price, $community->join_membercard);
        
        if (!$subPermDetail['perm']) {
            return $this->error('pay-community');
        }

        $member_id = hg_is_same_member($this->member['id'],$this->shop['id']);
        $community_user = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('id')])
            ->whereIn('member_id',$member_id)
            ->where(function ($query) {
                $query->where('expire', 0)
                    ->orWhere('expire', '>', time());
            })
            ->first();

        if($community_user){
            $this->error('already-join-community');
        }
        $params = [
            'shop_id'   => $this->shop['id'],
            'community_id'  => request('id'),
            'member_id'     => $this->member['id'],
            'member_name'   => $this->member['nick_name'],
            'expire' => $subPermDetail['expire_time'],
            'source' => 'free_subscribe',
        ];
        event(new JoinCommunityEvent($params));
        //已收藏的帖子恢复显示
        $note_ids = CommunityNote::where(['shop_id'=>$this->shop['id'],'community_id'=>request('id')])->pluck('hashid');
        $note_ids->isNotEmpty() && Collection::whereIn('content_id',$note_ids)->where('content_type','note')->update(['display'=>1]);

        $expireTime = $params['expire'] ? hg_format_date($params['expire']):null;
        return $this->output(['success'=>1,'expire_time'=>$expireTime]);
    }


    private function getCommunityMemberGag(){
        return MemberGag::where(['shop_id'=>$this->shop['id'],'content_id'=>request('community_id'),'content_type'=>'community','member_id'=>request('member_id')])->first();
    }

    /**
     * 会员禁言
     */
    public function memberGag(){
        $this->validateWithAttribute([
            'community_id'  => 'required|alpha_dash|size:12',
            'gag'           => 'required|numeric|in:0,1',
            'member_id'     => 'required|alpha_dash'
        ],[
            'community_id'  => '社群id',
            'gag'           => '禁言状态',
            'member_id'     => '成员id'
        ]);
        $member_gag = $this->getCommunityMemberGag();
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


}