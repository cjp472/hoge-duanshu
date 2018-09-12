<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/7/4
 * Time: 上午9:38
 */
namespace App\Http\Controllers\Manage\Content;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Comment;
use App\Models\Manage\Order;
use App\Models\Manage\Views;
use Illuminate\Support\Facades\DB;

class SingleController extends BaseController
{
    /**
     * 单个内容分析
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function SingleAnalysis()
    {
        $this->validateWith([
            'content_id'   => 'required|alpha_dash|size:12'
        ]);
        $start_time = strtotime(date('Y-m-d 00:00:00', time()));
        $todayOrder = $this->todayOrder($start_time); // 今日订单
        $allOrder = $this->allOrder(); // 总订单
        $todayView = $this->todayView($start_time); // 今日浏览
        $allView = $this->allView(); // 总浏览
        $todayComment = $this->todayComment($start_time); // 今日评论
        $allComment = $this->allComment(); // 总评论
        $todayIncome = $this->todayIncome($start_time); // 今日收入
        $allIncome = $this->allIncome(); // 总收入
        $data = [];
        $data['todayOrder'] = $todayOrder;
        $data['allOrder'] = $allOrder;
        $data['todayView'] = $todayView;
        $data['allView'] = $allView;
        $data['todayComment'] = $todayComment;
        $data['allComment'] = $allComment;
        $data['todayIncome'] = $todayIncome;
        $data['allIncome'] = $allIncome;
        return $this->output($data);
    }

    /**
     * 今日订单
     * @param $start_time
     * @return mixed
     */
    private function todayOrder($start_time)
    {
        $data = Order::where('content_id',request('content_id'))
            ->whereBetween('order_time',[$start_time,time()])
            ->select(DB::raw('count(id) as todayOrder'))
            ->pluck('todayOrder');
        return $data[0];
    }

    /**
     * 所有订单
     * @return mixed
     */
    private function allOrder()
    {
        $data = Order::where('content_id',request('content_id'))
            ->select(DB::raw('count(id) as allOrder'))
            ->pluck('todayOrder');
        return ($data[0] ? : 0);
    }

    /**
     * 今日浏览
     * @param $start_time
     * @return mixed
     */
    private function todayView($start_time)
    {
        $data = Views::where('content_id',request('content_id'))
            ->whereBetween('view_time',[$start_time,time()])
            ->select(DB::raw('count(id) as todayView'))
            ->pluck('todayView');
        return ($data[0] ? : 0);
    }

    /**
     * 所有浏览
     * @return mixed
     */
    private function allView()
    {
        $data = Views::where('content_id',request('content_id'))
            ->select(DB::raw('count(id) as todayView'))
            ->pluck('todayView');
        return ($data[0] ? : 0);
    }

    /**
     * 今日评论
     * @param $start_time
     * @return mixed
     */
    private function todayComment($start_time)
    {
        $data = Comment::where('content_id',request('content_id'))
            ->whereBetween('comment_time',[$start_time,time()])
            ->select(DB::raw('count(id) as todayComment'))
            ->pluck('todayComment');
        return ($data[0] ? : 0);
    }

    /**
     * 所有评论
     * @return mixed
     */
    private function allComment()
    {
        $data = Comment::where('content_id',request('content_id'))
            ->select(DB::raw('count(id) as todayComment'))
            ->pluck('todayComment');
        return ($data[0] ? : 0);
    }

    /**
     * 今日收入
     * @param $start_time
     * @return mixed
     */
    private function todayIncome($start_time)
    {
        $data = Order::where('content_id',request('content_id'))
            ->whereBetween('pay_time',[$start_time,time()])
            ->select(DB::raw('sum(price) as todayIncome'))
            ->pluck('todayIncome');
        return ($data[0] ? : 0);
    }

    /**
     * 所有收入
     * @return mixed
     */
    private function allIncome()
    {
        $data = Order::where('content_id',request('content_id'))
            ->select(DB::raw('sum(price) as todayIncome'))
            ->pluck('todayIncome');
        return ($data[0] ? : 0);
    }
}