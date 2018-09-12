<?php

namespace App\Http\Controllers\Manage\Notice;

use App\Events\SystemEvent;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Notice;
use App\Models\UserShop;

class NoticeController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * 系统消息详情
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function systemDetail($id)
    {
        $data = Notice::find($id);
        $data && $data->send_time && $data->send_time = hg_format_date($data->send_time);
        $data->shop_title = $data->shop ? $data->shop->title : '';
        return $this->output($data);
    }

    /**
     * 新建系统消息
     * @return \Illuminate\Http\JsonResponse
     */
    public function systemCreate()
    {
        $this->validateWith([
            'shop_id'    => 'required|alpha_dash',
            'title'     => 'required',
            'content'   => 'required',
            'type'      => 'required|numeric|in:0,1',
            'user_id'   => 'required|numeric',
            'user_name' => 'required|alpha_dash'
        ]);
        event(new SystemEvent(request('shop_id'),request('title'),request('content'),request('type'),request('user_id'),request('user_name')));
        return $this->output([
            'title' => request('title'),
            'send_time' => date('Y-m-d H:i:s'),
            'user_name' => request('user_name'),
            'id'        => 0
        ]);
    }

    /**
     * 更新系统消息
     * @return \Illuminate\Http\JsonResponse
     */
    public function systemUpdate()
    {
        $this->validateWith([
            'shop_id'    => 'required|alpha_dash',
            'title'     => 'required',
            'content'   => 'required',
            'id'      => 'required|numeric',
        ]);

        $notice = Notice::find(request('id'));
        $notice->title = request('title');
        $notice->content = request('content');
        $notice->save();
        return $this->output(['successs'=>1]);
    }


    /**
     * 系统信息列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function lists()
    {
        $this->validateWith([
            'shop_id'    => 'alpha_dash',
            'count' => 'numeric',
            'title' => 'alpha_dash'
        ]);
        $count = request('count') ?: 15;
        $sql = Notice::select('*');
        request('title') && $sql->where('title', 'like', '%'.request('title').'%');
        request('shop_id') && $sql->where('shop_id', request('shop_id'))->orWhere('shop_id',-1);
        $page = $sql->orderBy('send_time', 'desc')->paginate($count);

        $data = $this->listToPage($page);
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $item->send_time = $item->send_time ? hg_format_date($item->send_time) : '';
                $item->shop_title = $item->shop ? $item->shop->title : '';
            }
        }
        return $this->output($data);
    }


    /**
     * 系统消息删除(修改状态)
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete()
    {
        $this->validateWith([
            'id'   =>   'required|numeric'
        ]);
        $del = request('del') ? 1 : 0;
        Notice::where('id',request('id'))->update(['is_del'=>$del]);
        return $this->output(['success'=>1]);
    }
}
