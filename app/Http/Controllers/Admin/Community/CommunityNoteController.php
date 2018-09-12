<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2018/3/7
 * Time: 下午2:10
 */
namespace App\Http\Controllers\Admin\Community;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\CommunityNote;
use App\Models\CommunityUser;
use Illuminate\Support\Facades\Redis;
use Vinkla\Hashids\Facades\Hashids;

class CommunityNoteController extends BaseController{

    /**
     * @return \Illuminate\Http\JsonResponse
     * 帖子新增
     */
    public function create(){
        $data = $this->validateCommunityNote();
        $communityNote = new CommunityNote();
        $communityNote->setRawAttributes($data);
        $communityNote->save();
        $hashid = Hashids::encode($communityNote->id);
        $communityNote->hashid = $hashid;
        $communityNote->save();
        return $this->output($communityNote);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 帖子更新
     */
    public function update(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required'],['id'=>'帖子id','community_id'=>'社群id']);
        $communityNote = CommunityNote::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        $data = $this->validateCommunityNote();
        $communityNote->setRawAttributes($data);
        $communityNote->save();
        return $this->output($communityNote);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 帖子列表
     */
    public function lists(){
        $sql = CommunityNote::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id')]);
        request('title') && $sql->where('title','like','%'.request('title').'%');
        if(request('style') == 'boutique'){
            $sql->where('style',request('style'));
        }elseif(request('style') == 'other'){
            $sql->where('style','!=','boutique');
        }
        $display = request('display');
        isset($display) && intval($display)==0 && $sql->where('display',0);
        isset($display) && intval($display)==1 && $sql->where('display',1);
        $lists = $sql->orderByDesc('top')->orderByDesc('top_time')->orderByDesc('created_at')->paginate(request('count')?:10);
        $communityNote = $this->listToPage($lists);
        if($communityNote && $communityNote['data']){
            foreach ($communityNote['data'] as $item) {
                $item->note_id = $item->hashid;
                $item->boutique = intval($item->boutique);
                $item->is_gag = intval($item->is_gag);
                $item->top = intval($item->top);
                $item->display = intval($item->display);
                $item->annex_num = intval($item->annex_num);
                $item->create_role = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id'),'member_id'=>$item->create_id])->value('role');
            }
        }
        return $this->output($communityNote);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 帖子详情
     */
    public function detail(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required'],['id'=>'帖子id','community_id'=>'社群id']);
        $communityNote = CommunityNote::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        $communityNote->indexpic = $communityNote->indexpic?unserialize($communityNote->indexpic):[];
        $communityNote->annex = $communityNote->annex?unserialize($communityNote->annex):[];
        $communityNote->note_id = $communityNote->hashid;
        $communityNote->boutique = intval($communityNote->boutique);
        $communityNote->is_gag = intval($communityNote->is_gag);
        $communityNote->top = intval($communityNote->top);
        $communityNote->display = intval($communityNote->display);
        $communityNote->annex_num = intval($communityNote->annex_num);
        $communityNote->create_avatar = $communityNote->member?$communityNote->member->avatar:'';
        $communityNote->create_role = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id'),'member_id'=>$communityNote->create_id])->value('role');
        return $this->output($communityNote);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 帖子置顶
     */
    public function top(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required','top'=>'required'],['id'=>'帖子id','community_id'=>'社群id','top'=>'置顶状态']);
        $communityNote = CommunityNote::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        $communityNote->top = request('top');
        $communityNote->top_time = request('top') == 0 ? 0 : time();
        $communityNote->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 帖子设为精选
     */
    public function boutique(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required','boutique'=>'required'],['id'=>'帖子id','community_id'=>'社群id','boutique'=>'精选状态']);
        $communityNotice = CommunityNote::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        $communityNotice->boutique = request('boutique');
        intval(request('boutique'))==1 && $communityNotice->style = 'boutique';
        intval(request('boutique'))==0 && $communityNotice->style = '';
        $communityNotice->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 帖子隐藏/显示
     */
    public function display(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required','display'=>'required'],['id'=>'帖子id','community_id'=>'社群id','display'=>'显示状态']);
        $communityNotice = CommunityNote::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        $communityNotice->display = request('display');
        $communityNotice->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 帖子删除（可批量）
     */
    public function delete(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required'],['id'=>'帖子id','community_id'=>'社群id']);
        $id = request('id');
        $ids = explode(',',$id);
        CommunityNote::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id')])->whereIn('hashid',$ids)->delete();
        //删除帖子收藏
        Collection::whereIn('content_id',$ids)->where('content_type','note')->delete();
        //删除帖子评论
        Comment::whereIn('content_id',$ids)->where('content_type','note')->delete();
        if($ids){
            foreach ($ids as $item){
                //删除点赞状态信息
                Redis::del('note:praise:status:'.$item);
            }
        }
        return $this->output(['success'=>1]);
    }


    private function validateCommunityNote(){
        $this->validateWithAttribute([
            'title'=>'required|max:40',
            'content'=> 'max:1000',
            'community_id'=> 'required',
        ],[
            'title' => '名称',
            'content' => '内容',
            'community_id' => '社群id',
        ]);
        $data = [
            'shop_id' => $this->shop['id'],
            'community_id' => request('community_id'),
            'title' => request('title'),
            'content' => request('content'),
            'indexpic' => request('indexpic')?serialize(request('indexpic')):'',
            'annex' => request('annex')?serialize(request('annex')):'',
            'annex_num'=> request('annex')?count(request('annex')):0,
            'boutique' => request('boutique')?:0,
            'style' => request('style')?:'',
            'display' => 1,
        ];
        request('boutique') == 1 && $data['style'] = 'boutique';
        $admin = CommunityUser::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id'),'role'=>'admin'])->first();
        $data['create_id'] = $admin->member_id;
        $data['create_name'] = $admin->member_name;
        return $data;
    }


}