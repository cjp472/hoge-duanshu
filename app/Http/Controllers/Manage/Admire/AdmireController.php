<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/9/6
 * Time: 上午10:26
 */
namespace App\Http\Controllers\Manage\Admire;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\AdmireOrder;

class AdmireController extends BaseController
{
    /**
     * 赞赏列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listAdmire()
    {
        $this->validateWithAttribute([
            'nickname'      => 'alpha_dash',
            'lecturer_name' => 'alpha_dash',
            'pay_status'    => 'numeric',
            'count'         => 'numeric'
        ],[
            'nickname'      => '赞赏人名称',
            'lecturer_name' => '讲师名称',
            'pay_status'    => '支付状态',
            'count'         => '个数'
        ]);
        $count = request('count') ? : 15;
        $admire = AdmireOrder::select('id','shop_id','nickname','avatar','content_id','lecturer_name','pay_status','price','order_time');
        request('nickname') && $admire->where('nickname','like','%'.request('nickname').'%');
        request('lecturer_name') && $admire->where('lecturer_name','like','%'.request('lecturer_name').'%');
        array_key_exists('pay_status',request()->input()) && $admire->where('pay_status',request('pay_status'));
        $data = $admire->orderBy('order_time','desc')->paginate($count);
        if ($data->items()) {
            foreach ($data->items() as $item) {
                $item->shop_name = $item->belongContent ? $item->belongContent->title : '';
                $item->content_name = $item->belongShop ? $item->belongShop->title : '';
                $item->avatar = hg_unserialize_image_link($item->avatar);
                $item->order_time = hg_format_date($item->order_time);
                $item->pay_status = intval($item->pay_status);
            }
        }
        return $this->output($this->listToPage($data));

    }

    /**
     * 赞赏详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailAdmire()
    {
        $this->validateWithAttribute([
            'id'    => 'required|numeric'
        ],[
            'id'    => 'id'
        ]);
        $admire = AdmireOrder::where('id',request('id'))->firstOrFail();
        $admire->avatar = hg_unserialize_image_link($admire->avatar);
        $admire->order_time = hg_format_date($admire->order_time);
        $admire->pay_status = intval($admire->pay_status);
        return $this->output($admire);
    }

    /**
     * 赞赏统计
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function totalAdmire()
    {
        $endTime = time();
        $startTime = strtotime(date('Y-m-d,00:00:00'));
        $allMoney = AdmireOrder::where('pay_status',0)->sum('price');
        $todayMoney = AdmireOrder::where('pay_status',0)->whereBetween('order_time',[$startTime,$endTime])->sum('price');
        $allNumber = AdmireOrder::where('pay_status',0)->count('id');
        $todayNumber = AdmireOrder::where('pay_status',0)->whereBetween('order_time',[$startTime,$endTime])->count('id');
        $data = [
            'allMoney'    => $allMoney,
            'todayMoney'  => $todayMoney,
            'allNumber'   => $allNumber,
            'todayNumber' => $todayNumber
        ];
        return $this->output($data);
    }
}