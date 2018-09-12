<?php
namespace App\Http\Controllers\Manage\Notice;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\MemberMessage;
use App\Models\Manage\MemberNotice;

class MemberNoticeController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     *根据条件查询所有会员信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function lists()
    {
        $this->validateWith([
            'count'        => 'numeric',
            'content'      => 'string',
            'start_time'   => 'date',
            'end_time'     => 'date',
            'type'         => 'numeric|in:0,1',
            'status'       => 'numeric|in:0,1,2,3',
            'shop_id'      => 'alpha_dash'
        ]);
        $count = request('count') ? : 15;
        $sql = MemberNotice::select('sender','sender_name','recipients','recipients_name','content','send_time','status','type');
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();

        request('shop_id') && $sql->where('shop_id',request('shop_id'));
        request('content') && $sql->where('content','like','%'.request('content').'%');
        request('sender_name') && $sql->where('sender_name','like','%'.request('sender_name').'%');
        request('recipients_name') && $sql->where('recipients_name','like','%'.request('recipients_name').'%');
        $sql->whereBetween('send_time',[$start_time,$end_time]);
        array_key_exists('status',request()->input()) && $sql->where('status',request('status'));
        array_key_exists('type',request()->input()) && $sql->where('type',request('type'));
        $notice = $sql->orderBy('send_time','desc')->paginate($count);
        foreach ($notice as $item) {
            $item->send_time = $item->send_time ? hg_format_date($item->send_time) : '';
        }
        return $this->output($this->listToPage($notice));
    }

    /**
     * 删除会员消息(修改状态)
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete()
    {
        $this->validateWith([
            'member_id'   => 'required|alpha_dash',
            'notify_id'   => 'required|numeric',
            'num'         => 'required|numeric|in:0,1'
        ]);
        MemberMessage::where(['notify_id'=>request('notify_id'),'member_id'=>request('member_id')])
            ->update(['is_del'=>request('num')]);
        return $this->output(['success'=>1]);
    }
}