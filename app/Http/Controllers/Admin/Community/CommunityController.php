<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2018/3/6
 * Time: 下午2:41
 */
namespace App\Http\Controllers\Admin\Community;

use App\Events\JoinCommunityEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityNote;
use App\Models\CommunityNotice;
use App\Models\CommunityUser;
use Vinkla\Hashids\Facades\Hashids;


class CommunityController extends BaseController{

    /**
     * @return \Illuminate\Http\JsonResponse
     * 新增社群
     */
    public function create(){
        $data = $this->communityValidate();
        $community = new Community();
        $community->setRawAttributes($data);
        $community->save();
        $hashid = Hashids::encode($community->id);
        $community->hashid = $hashid;
        $community->save();
        $user = $this->communityUserValidate($hashid);
        event(new JoinCommunityEvent($user));
        return $this->output($community);
    }

    /**
     * @param $hashid
     * @return array
     * 管理员数据处理
     */
    private function communityUserValidate($hashid){
        return  [
            'shop_id' => $this->shop['id'],
            'community_id' => $hashid,
            'member_id' => request('member_id'),
            'member_name' => request('member_name'),
            'role'  => 'admin',
            'source' => 'admin_setting',
            'top'  => 1,
        ];
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 更新社群
     */
    public function update(){
        $this->validateWithAttribute(['id'=>'required'],['id'=>'社群id']);
        $data = $this->communityValidate();
        $community = Community::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id']])->first();
        $community->setRawAttributes($data);
        $community->save();
        $user = $this->communityUserValidate(request('id'));
        event(new JoinCommunityEvent($user));
        return $this->output($community);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 社群列表
     */
    public function lists(){
        $sql = Community::where(['shop_id'=>$this->shop['id']]);
        request('title') && $sql->where('title','like','%'.request('title').'%');
        $lists = $sql->orderBy('created_at','desc')->paginate(request('count')?:10);
        $community = $this->listToPage($lists);
        if($community && $community['data']){
            foreach ($community['data'] as $item){
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : [];
                $item->note_num = count($item->communityNote);
                $item->community_id = $item->hashid;
                $item->display = intval($item->display);
            }
        }
        return $this->output($community);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * 社群详情
     */
    public function detail($id){
        $community = Community::where(['shop_id'=>$this->shop['id'],'hashid'=>$id])->first();
        $community->indexpic = unserialize($community->indexpic);
        $community->community_id = $community->hashid;
        $community->user_count = count($community->communityUser);
        $community->note_count = count($community->communityNote);
        $community->display = intval($community->display);
        $community->admin = $this->processCommunityAdmin($id);
        return $this->output($community);
    }

    private function processCommunityAdmin($id){
        $admin = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>$id,'role'=>'admin'])->first();
        return [
            'member_id' => $admin?$admin->member_id:'',
            'member_name' => $admin?$admin->member_name:'',
            'avatar' => $admin?($admin->member?$admin->member->avatar:[]):'',
            'sex' => $admin?($admin->member?$admin->member->sex:0):0,
        ];
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 社群显示/隐藏
     */
    public function display($id){
        $communityNotice = Community::where(['shop_id'=>$this->shop['id'],'hashid'=>$id])->first();
        $communityNotice->display = request('display');
        $communityNotice->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * 社群删除
     */
    public function delete($id)
    {
        $community = Community::where(['shop_id'=>$this->shop['id'],'hashid'=>$id])->first();
        if($community){
            $community->delete();

            $note_ids = CommunityNote::where('community_id',$id)->pluck('hashid');
            //删除 帖子
            CommunityNote::where(['community_id'=>$id,'shop_id'=>$this->shop['id']])->delete();
            //删除公告
            CommunityNotice::where(['community_id'=>$id,'shop_id'=>$this->shop['id']])->delete();
            //删除成员
            CommunityUser::where(['community_id'=>$id,'shop_id'=>$this->shop['id']])->delete();
            //删除收藏数据
            Collection::whereIn('content_id',$note_ids)->where('content_type','note')->delete();
            //删除评论数据
            Comment::whereIn('content_id',$note_ids)->where('content_type','note')->delete();

        }else{
            $this->error('no_community');
        }
        return $this->output(['success'=>1]);
    }

    //数据验证
    private function communityValidate()
    {
        $this->validateWithAttribute([
            'title'      => 'required|max:50',
            'brief'      => 'required|max:500',
            'indexpic'   => 'required',
            'member_id'   => 'required',
            'member_name'   => 'required',
        ],[
            'title'      => '名称',
            'brief'      => '描述',
            'indexpic'   => '索引图',
            'pay_type'   => '收费类型',
            'display'    => '显示状态',
            'member_id'    => '会员id',
            'member_name'    => '会员昵称',
        ]);
        $data = [
            'shop_id'   => $this->shop['id'],
            'title'     => request('title'),
            'brief'     => request('brief'),
            'indexpic'  => serialize(request('indexpic')),
            'pay_type'  => request('pay_type') ? : 0,
            'price'     => request('price') ? : 0,
            'authority' => request('authority') ? : 'all',
            'display'   => request('display'),
        ];
        if(request('price')>MAX_ORDER_PRICE){
            return $this->error('max-price-error');
        }
        return $data;
    }

}