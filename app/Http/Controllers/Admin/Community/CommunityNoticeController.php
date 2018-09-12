<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2018/3/6
 * Time: 下午5:13
 */
namespace App\Http\Controllers\Admin\Community;

use App\Http\Controllers\Admin\BaseController;
use App\Models\CommunityNotice;
use Vinkla\Hashids\Facades\Hashids;

class CommunityNoticeController extends BaseController{


    /**
     * @return \Illuminate\Http\JsonResponse
     * 新增社群公告
     */
    public function create(){
        $data = $this->noticeValidate();
        $communityNotice = new CommunityNotice();
        $communityNotice->setRawAttributes($data);
        $communityNotice->save();
        $hashid = Hashids::encode($communityNotice->id);
        $communityNotice->hashid = $hashid;
        $communityNotice->save();
        return $this->output($communityNotice);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 更新社群公告
     */
    public function update(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required'],['id'=>'公告id','community_id'=>'社群id']);
        $data = $this->noticeValidate();
        $communityNotice = CommunityNotice::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id'],'community_id'=>request('community_id')])->first();
        $communityNotice->setRawAttributes($data);
        $communityNotice->save();
        return $this->output($communityNotice);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 社群公告列表
     */
    public function lists(){
        $sql = CommunityNotice::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id')]);
        request('title') && $sql->where('title','like','%'.request('title').'%');
        isset($display) && intval($display)==0 && $sql->where('display',0);
        isset($display) && intval($display)==1 && $sql->where('display',1);
        $lists = $sql->orderByDesc('top')->orderByDesc('top_time')->orderByDesc('created_at')->paginate(request('count')?:10);
        $communityNotice = $this->listToPage($lists);
        if($communityNotice && $communityNotice['data']){
            foreach ($communityNotice['data'] as $item) {
                $item->notice_id = $item->hashid;
                $item->top = intval($item->top);
                $item->display = intval($item->display);
            }
        }
        return $this->output($communityNotice);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 社群公告详情
     */
    public function detail(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required'],['id'=>'公告id','community_id'=>'社群id']);
        $communityNotice = CommunityNotice::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        $communityNotice->notice_id = $communityNotice->hashid;
        $communityNotice->top = intval($communityNotice->top);
        $communityNotice->display = intval($communityNotice->display);
        return $this->output($communityNotice);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 社群公告置顶
     */
    public function top(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required','top'=>'required'],['id'=>'公告id','community_id'=>'社群id','top'=>'置顶状态']);
        $communityNotice = CommunityNotice::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        $communityNotice->top = request('top');
        $communityNotice->top_time = request('top') == 0 ? 0 : time();
        $communityNotice->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 公告显示/隐藏
     */
    public function display(){
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required','display'=>'required'],['id'=>'公告id','community_id'=>'社群id','display'=>'显示状态']);
        $communityNotice = CommunityNotice::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id'),'community_id'=>request('community_id')])->first();
        $communityNotice->display = request('display');
        $communityNotice->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * 社群公告删除（可批量）
     */
    public function delete()
    {
        $this->validateWithAttribute(['id'=>'required','community_id'=>'required'],['id'=>'公告id','community_id'=>'社群id']);
        $id = request('id');
        $ids = explode(',',$id);
        CommunityNotice::where(['shop_id'=>$this->shop['id'],'community_id'=>request('community_id')])->whereIn('hashid',$ids)->delete();
        return $this->output(['success'=>1]);
    }

    //数据验证
    private function noticeValidate()
    {
        $this->validateWithAttribute([
            'title'      => 'required|max:80',
            'content'    => 'required|max:2000',
            'community_id'=>'required',
        ],[
            'title'      => '名称',
            'content'    => '内容',
            'display'    => '显示状态',
            'community_id'=>'社群id',
        ]);
        $data = [
            'shop_id'   =>$this->shop['id'],
            'community_id'=>request('community_id'),
            'title'     => request('title'),
            'content' => request('content'),
            'display' => 1,
            'top' => 0,
        ];
        return $data;
    }


}