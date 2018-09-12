<?php
/**
 * 意见反馈
 */
namespace App\Http\Controllers\Admin\Feedback;

use App\Http\Controllers\Admin\BaseController;
use App\Models\FeedBack;
use Illuminate\Support\Facades\DB;

class FeedbackController extends BaseController
{
    /**
     * 反馈列表
     * @return mixed
     */
    public function lists()
    {
        $this->validateWithAttribute(['count' => 'numeric','content' => 'string' ,'nick_name' => 'string'],['count'=>'每页条数','content'=>'内容','nick_name'=>'昵称']);
        $count= request('count') ? : 10;
//        $feedback = FeedBack::where('feedback.shop_id','YO08MReGZkyp');
        $feedback = FeedBack::where('feedback.shop_id',$this->shop['id']);
        request('content') && $feedback->where('content','like','%'.request('content').'%');    //查询参数
        request('nick_name') && $feedback->where('nick_name','like','%'.request('nick_name').'%');
        request('source') && $feedback->where('source','=',request('source'));
        if(request('start_date') && !request('end_date')) $feedback->whereBetween('feedback_time',[strtotime(request('start_date')),time()]);
        if(!request('start_date') && request('end_date')) $feedback->whereBetween('feedback_time',[0,strtotime(request('end_date'))]);
        if(request('start_date') && request('end_date'))  $feedback->whereBetween('feedback_time',[strtotime(request('start_date')),strtotime(request('end_date'))]);

        $result = $feedback
            ->leftJoin('member','feedback.member_id','=','member.uid')
            ->where('member.shop_id',$this->shop['id'])
            ->orderBy('feedback.feedback_time','desc')
            ->select('feedback.id','content','member_id','feedback_time','reply_time','contact_way')
            ->paginate($count);
        $data = $this->listToPage($result);
        foreach ($data['data'] as $item){
            $item->feedback_time = $item->feedback_time ? date('Y-m-d H:i:s',$item->feedback_time) : '';
            $item->reply_time = $item->reply_time ? date('Y-m-d H:i:s',$item->reply_time) : '';
            $item->avatar = $item->belongsToMember ? $item->belongsToMember->avatar : '';
            $item->nick_name = $item->belongsToMember ? $item->belongsToMember->nick_name : '';
            $item->source = $item->belongsToMember ? $item->belongsToMember->source : '';
            $item->contact_way = $item->contact_way ?  : '';
        }
        return $this->output($data);
    }

    /**
     * 关于某个用户的反馈列表
     */
    public function userFeedback(){
        $this->validateWithAttribute(['member_id' => 'required|alpha_dash|size:32','count' => 'numeric'],['member_id'=>'会员id','count'=>'每页条数']);
        $count= request('count') ? : 10;
//        $result = FeedBack::where('shop_id','YO08MReGZkyp')
        $result = FeedBack::where('shop_id',$this->shop['id'])
            ->where('member_id',request('member_id'))
            ->select('id','feedback_time','content','contact_way')
            ->paginate($count);
        foreach ($result as $item){
            $item->contact_way = $item->contact_way ? : '';
            $item->feedback_time = $item->feedback_time ? date('Y-m-d H:i:s',$item->feedback_time) : '';
        }
        $data = $this->listToPage($result);
        return $this->output($data);
    }

    /**
     * 更新回复时间
     */
    public function updateTime(){
        $this->validateWithAttribute(['id' => 'required|numeric'],['id'=>'反馈id']);
        $result = FeedBack::where('id',request('id'))
            ->update(['reply_time' => time()]);
        if($result){
            return $this->output(['success' => 1]);
        }else{
            $this->error('update-fail');
        }
    }


}