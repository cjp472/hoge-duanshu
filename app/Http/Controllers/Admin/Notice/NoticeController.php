<?php
/**
 * 消息管理
 */
namespace App\Http\Controllers\Admin\Notice;

use App\Http\Controllers\Admin\BaseController;
use App\Models\MemberNotify;
use App\Models\Notice;

class NoticeController extends BaseController
{
    /**
     * 消息列表
     */
    public function lists()
    {
        $this->validateWithAttribute([
            'page'      => 'numeric',
            'count'     => 'numeric|',
            'type'      => 'numeric|in:0,1',
            'content'   => 'alpha_dash|max:256',
            'sender'    => 'alpha_dash|max:32',
            'recipients'=> 'alpha_dash|max:32',
            'start_time' => 'date',
            'end_time'   => 'date',
            'status'     => 'numeric|in:1,2,3'
        ],[
            'page'      => '页数',
            'count'     => '每页数目',
            'type'      => '消息类型',
            'content'   => '消息内容',
            'sender'    => '发送人',
            'recipients'=> '接收人',
            'start_time' => '搜索开始时间',
            'end_time'   => '搜索结束时间',
        ]);
        $list = $this->getNotifyList();
        if($list && is_array($list['data'])){
            foreach($list['data'] as $item){
                if($item->send_time > time()){
                    $item->status = 3;
                }
                $item->send_time = $item->send_time ? date('Y-m-d H:i:s',$item->send_time) : '';
                $item->recipients_name = $item->recipients == -1 ? '所有人' : ($item->member ? $item->member->nick_name : '');
                $item->link_info = $item->link_info ? unserialize($item->link_info) : [];
            }
        }
        return $this->output($list);
    }

    /**
     * 消息详情
     * @param $id
     * @return mixed
     */
    public function detail($id){
        $notice = Notice::findOrFail($id);
        $notice->send_time = $notice->send_time ? date('Y-m-d H:i:s',$notice->send_time) : '';
        $notice->link_info = $notice->link_info ? unserialize($notice->link_info) : [];
        $notice->recipients_name = $notice->member ? $notice->member->nick_name : [];
        return $this->output($notice);

    }

    /**
     * 获取消息列表数据
     * @return array
     */
    private function getNotifyList(){
        $notice = Notice::where('notify.shop_id',$this->shop['id']);
        array_key_exists('type',request()->input()) && $notice->where('type',request('type'));
        if(array_key_exists('status',request()->input())){
            if(request('status') == 3) {
                $notice->where('send_time','>',time());
            }else{
                $notice->where('status','=',intval(request('status')))
                ->where('send_time','<',time());
            }
        }
        request('content') && $notice->where('content','like','%'.request('content').'%');
        request('sender') && $notice->where('sender_name','like','%'.request('sender').'%');
        request('recipients') && $notice->leftJoin('member', 'member.uid', '=', 'notify.recipients')
            ->where(function ($query) {
                return $query->where('member.nick_name', 'like', '%' . request('recipients') . '%')
                    ->orwhere('recipients_name', 'like', '%' . request('recipients') . '%');
            });
        request('start_time') && $notice->where('send_time','>',strtotime(request('start_time')));
        request('end_time') && $notice->where('send_time','<',strtotime(request('end_time')));
        $count = request('count') ? : 20;
        $data = $notice->where('notify.shop_id',$this->shop['id'])
            ->orderBy('send_time','desc')
            ->select('notify.*')
            ->paginate($count);
        return $this->listToPage($data);
    }

    /**
     * 发送单条消息
     */
    public function sendToOne()
    {
        $this->validateWithAttribute([
            'sender_name'   => 'required|alpha_dash|max:32',
            'recipients'    => 'required|alpha_dash|size:32',
            'content'       => 'required|max:256',
            'link'          => 'array|size:4'
        ],[
            'sender_name'   => '发送人名称',
            'recipients'    => '接收人',
            'content'       => '消息内容',
            'link'          => '消息跳转'
        ]);
        $param = $this->sendToOneParam();
        $this->sendOne($param);
        return $this->output(['success'=>1]);
    }


    /**
     * 格式化单条消息参数
     * @param string $recipients
     * @param int $type
     * @return array
     */
    private function sendToOneParam($recipients='',$type=0)
    {
        $notify = [
            'shop_id'       => $this->shop['id'],
            'sender'        => $this->user['id'],
            'sender_name'   => request('sender_name') ? : '',
            'recipients'    => $recipients ? : request('recipients'),
            'recipients_name'   => intval($type) ? '所有人' : request('recipients_name'),
            'content'       => addslashes(trim(request('content'))),
            'send_time'     => request('send_time') ? strtotime(request('send_time')) : time(),
            'type'          => $type,
            'link_info'     => serialize([
                'title'     => request('link.title') ? : '',
                'type'      => request('link.type'),
                'content_id'=> request('link.type') != 'outLink' ? request('link.content_id') : '',
                'out_link'  => request('link.type') == 'outLink' ? trim(request('link.out_link')) : '',
            ]),
        ];
        return $notify;
    }

    /**
     * 单条发送
     * @param $param
     */
    private function sendOne($param){
        $notify_id = Notice::insertGetId($param);
        if($notify_id){
            $notice = Notice::findOrFail($notify_id);
            $notice->status = 1;
            $notice->saveOrFail();
            MemberNotify::insert([
                'member_id' => $param['recipients'] ? : 0,
                'notify_id' => $notify_id,
            ]);
        }

    }

    /**
     * 推送全员消息
     */
    public function sendToAll()
    {
        $this->validateWithAttribute([
            'send_time'     => 'required|date',
            'sender_name'   => 'required|alpha_dash|max:32',
            'content'       => 'required|max:256',
            'link'          => 'array|size:4',
        ],[
            'sender_name'   => '发送人名称',
            'send_time'     => '发送时间',
            'content'       => '消息内容',
            'link'          => '消息跳转'
        ]);
        $this->sendToAllParam();
        return $this->output(['success'=>1]);

    }

    /**
     * 推送全员消息
     */
    private function sendToAllParam()
    {
        $notify = new Notice();
        $param = $this->sendToOneParam(-1, 1);
        $notify->setRawAttributes($param);
        $notify->save();
        return $notify;
    }

    /**
     * 修改推送全员
     * @param $id
     * @return mixed
     */
    public function updateSendToAll($id){
        $this->validateWithAttribute([
            'send_time'     => 'required|date',
            'sender_name'   => 'required|alpha_dash|max:32',
            'content'       => 'required|max:256',
            'link'          => 'array|size:4',
        ],[
            'sender_name'   => '发送人名称',
            'send_time'     => '发送时间',
            'content'       => '消息内容',
            'link'          => '消息跳转'
        ]);
        $notice = Notice::where(['shop_id'=>$this->shop['id'],'type'=>1])->findOrFail($id);
        $notice->send_time < time() && $this->error('already_send');
        $send_time = strtotime(request('send_time'));
        $notice->send_time = $send_time;
        if($send_time <= time()){
            $notice->send_time = time();
        }
        $notice->sender_name = request('sender_name');
        $notice->content = request('content');
        $notice->link_info = serialize([
            'title'     => request('link.title') ? : '',
            'type'      => request('link.type'),
            'content_id'=> request('link.type') != 'outLink' ? request('link.content_id') : '',
            'out_link'  => request('link.type') == 'outLink' ? trim(request('link.out_link')) : '',
        ]);
        $notice->save();
        return $this->output(['success'=>1]);
    }

    /**
     * 消息撤回
     */
    public function revoke()
    {
        $this->validateWithAttribute([
            'id' => 'required|numeric',
        ],[
            'id'    => '消息id'
        ]);
        $notice = Notice::where(['shop_id'=>$this->shop['id'],'status'=>1])->findOrFail(request('id'));
        $notice->status = 2;
        $notice->saveOrFail();
        MemberNotify::where(['notify_id'=>request('id')])->update(['is_del'=>1]);
        return $this->output(['success'=>1]);


    }

    /**
     * 查看某个用户的消息
     */
    public function userList()
    {
        $this->validateWithAttribute([
            'member_id' => 'required|alpha_dash|size:32',
            'page'      => 'numeric',
            'count'     => 'numeric|max:10000',
        ],[
            'member_id' => '用户id',
            'page'      => '页数',
            'count'     => '每页数目',
        ]);
        $notice = $this->getUserList();
        if($notice && is_array($notice['data'])){
            foreach ($notice['data'] as $item) {
                $item->send_time = $item->send_time ? date('Y-m-d H:i:s',$item->send_time) : '';
                $item->content = $item->content ? stripslashes($item->content) : '';
            }
        }

        return $this->output($notice);

    }

    /**
     * 获取用户消息列表
     * @return array
     */
    private function getUserList(){
        $where = [
            'recipients' => request('member_id'),
        ];
        $count = request('count') ? : 20;
        $notice = Notice::where(function($query) use ($where){
            $query->where($where)->orWhere('recipients',-1);
        })
            ->where('shop_id',$this->shop['id'])
            ->where(['status'=>1,'type'=>0])
            ->orderBy('send_time','desc')
            ->paginate($count,['id','sender_name','send_time','content','type']);
        return $this->listToPage($notice);
    }


}