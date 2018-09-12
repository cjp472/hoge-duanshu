<?php
/**
 * 赠送码.
 * User: Allen
 * Date: 17/6/23
 * Time: 下午3:03
 */

namespace App\Http\Controllers\Manage\Code;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Code;
use App\Models\Manage\InviteCode;

class CodeController extends BaseController
{

    /**
     * 自建赠送码列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function selfCodeList()
    {
        $this->validateWith([
            'title'        =>   'string',
            'count'        =>   'numeric',
            'start_time'   =>   'date',
            'end_time'     =>   'date',
            'status'       =>   'numeric',
            'shop_id'      =>   'alpha_dash'
        ]);
        $count = request('count') ? : 10;
        $sql = InviteCode::where('type','self');
        $start_time = request('start_time') ? request('start_time') : '0000-00-00 00:00:00';
        $end_time = request('end_time') ? request('end_time') : date('Y-m-d H:i:s');
        $sql->whereBetween('created_at',[$start_time,$end_time]);
        request('shop_id') && $sql->where('shop_id',request('shop_id'));
        request('title') && $sql->where('title','like','%'.request('title').'%');
        (request('status') == 1) && $sql->where('start_time','>',time());  // 未开始
        (request('status') == 2) && $sql->where('start_time','<',time())->where('end_time','>',time());  // 进行中
        (request('status') == 3) && $sql->where('end_time','<',time());    // 已结束
        $selfCode = $sql->select('id','created_at','title','content_id','content_type','content_title','start_time','end_time','total_num','use_num')
            ->orderBy('created_at','desc')
            ->paginate($count);
        if ($selfCode->items()) {
            foreach ($selfCode->items() as $item) {
                ($item->start_time > time()) && $item->status = 1;
                ($item->start_time < time()) && ($item->end_time > time()) && $item->status = 2;
                ($item->end_time < time()) && $item->status = 3;
                $item->start_time = $item->start_time ? hg_format_date($item->start_time) : '';
                $item->end_time = $item->end_time ? hg_format_date($item->end_time) : '';
            }
        }
        return $this->output($this->listToPage($selfCode));
    }

    /**
     * invite_id对应下的所有code
     * @return \Illuminate\Http\JsonResponse
     */
    public function selfCodeDetail()
    {
        $this->validateWith([
            'code_id'    =>   'required|numeric',
            'code'       =>   'numeric',
            'user_name'  =>   'string',
            'status'     =>   'numeric',
            'count'      =>   'numeric',
            'start_time' =>   'date',
            'end_time'   =>   'date'
        ]);
        $count = request('count') ? : 10;
        $sql = Code::where('code_id',request('code_id'));
        request('code') && $sql->where('code','like','%'.request('code').'%');
        request('user_name') && $sql->where('user_name','like','%'.request('user_name').'%');
        array_key_exists('status',request()->input()) && $sql->where('status',request('status'));
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $sql->whereBetween('use_time',[$start_time,$end_time]);
        $selfCodeDetail = $sql->select('code','user_name','status','use_time','user_avatar')->orderBy('use_time','desc')->paginate($count);
        if ($selfCodeDetail->items()) {
            foreach ($selfCodeDetail->items() as $item) {
                $item->use_time = $item->use_time ? hg_format_date($item->use_time) : '';
            }
        }
        return $this->output($this->listToPage($selfCodeDetail));
    }

    /**
     * share的所有code详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function shareCodeList()
    {
        $this->validateWith([
            'content_title'  => 'string',
            'code'           => 'numeric',
            'order_id'       => 'numeric',
            'start_time'   =>   'date',
            'end_time'     =>   'date',
            'status'         => 'numeric',
            'count'          => 'numeric',
            'shop_id'    =>   'alpha_dash'
        ]);
        $count = request('count') ? : 10;
        $sql = InviteCode::where('invite_code.type','share')->join('code','code.code_id','=','invite_code.id');
        request('shop_id') && $sql->where('invite_code.shop_id',request('shop_id'));
        request('content_title') && $sql->where('invite_code.content_title','%'.request('content_title').'%');
        request('code') && $sql->where('invite_code.code','%'.request('code').'%');
        request('order_id') && $sql->where('invite_code.order_id','%'.request('order_id').'%');
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $sql->whereBetween('buy_time',[$start_time,$end_time]);
        array_key_exists('status',request()->input()) && $sql->where('code.status',request('status'));
        $shareCodeDetail = $sql->select('invite_code.type','invite_code.buy_time','invite_code.order_id','invite_code.content_title','code.user_avatar','code.code','code.status')->paginate($count);
        if ($shareCodeDetail->items()) {
            foreach ($shareCodeDetail->items() as $item) {
                $item->buy_time = $item->buy_time ? hg_format_date($item->buy_time) : '';
            }
        }
        return $this->output($this->listToPage($shareCodeDetail));
    }
}