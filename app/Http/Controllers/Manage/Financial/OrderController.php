<?php
/**
 * 财务管理-订单管理
 */
namespace App\Http\Controllers\Manage\Financial;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Content;
use App\Models\Manage\Order;
use App\Models\Manage\Shop;
use Illuminate\Http\Request;

class OrderController extends BaseController
{
    /**
     * 列表
     * @return mixed
     */
    public function lists()
    {
        $this->validateWith([
            'count'    => 'numeric',
            'order_id' => 'alpha_dash',
            'source'   => 'alpha_dash'
        ]);
        $count = request('count') ? : 15;
        $shop_id = request('shop_id');
        $sql = Order::select('*')->where('pay_status', request('pay_status'));
        request('order_id') && $sql->where('order_id', 'like', '%'.request('order_id').'%');
        request('source') && $sql->where('source',request('source'));
        $shop_id && $sql->where('shop_id',$shop_id);
        $result = $sql->orderBy('order_time','desc')->paginate($count);
        $data = $this->listToPage($result);
        if($data['data']) {
            foreach ($data['data'] as $item) {
                $item->order_time = hg_format_date($item->order_time);
            }
        }
        return $this->output($data);
    }

    /**
     * 收入统计
     * @return mixed
     */
    public function orderIncome(){
        $tIncome = $this->todayIncome();  //今日收入
        $aIncome = $this->allIncome();   //总收入
        return $this->output(['todayIncome' => $tIncome,'allIncome' =>$aIncome]);
    }

    /**
     * 获取会员的订单
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMemberOrder()
    {
        $this->validateWith([
            'count' => 'numeric',
            'page' => 'numeric',
            'member_id' => 'required|alpha_num'
        ]);
        $count = request('count') ?: 10;
        $order = Order::where([
            'user_id'   => request('member_id'),
            'pay_status'    => 1,
        ])->select('pay_time','content_title','content_type','price','order_type')
            ->orderby('pay_time','desc')
            ->paginate($count);
        $this->transTime($order->items());
        return $this->output($this->listToPage($order));
    }

    private function transTime($data)
    {
        foreach ($data as $v){
            $v->pay_time && $v->pay_time = date('Y-m-d H:i:s',$v->pay_time);
            $v->order_time && $v->order_time = date('Y-m-d H:i:s',$v->order_time);
        }
    }

    private function todayIncome(){
        $result = Order::where('pay_status',1)
            ->whereBetween('order_time',[strtotime(date('Y-m-d 00:00:00',time())),time()])
            ->sum('price');
        return $result;
    }

    private function allIncome(){
        $result = Order::where('pay_status',1)->sum('price');
        return $result;
    }

    public function topShop()
    {
        $count = request('count') ?: 10;
        $order= Order::where('pay_status',1)
            ->groupBy('shop_id')
            ->limit($count)
            ->selectRaw('sum(price) as total,count(*) as count')
            ->addSelect('shop_id')
            ->orderBy('total','desc')
            ->paginate($count);
        if($order->total() > 0){
            foreach ($order->items() as $item){
                $item->title =  Shop::where('hashid',$item->shop_id)->value('title');
            }
            return $this->output($order->items());
        }
        return $this->output([]);
    }

    public function topSubscribe()
    {
        $count = request('count') ?: 10;
        $top = Content::selectRaw('subscribe*price as total')->addSelect('subscribe as count','type','hashid','title','shop_id','price')->orderBy('subscribe','desc')->limit($count)->get();
        return $this->output($top);
    }


    public function topViewCount()
    {
        $count = request('count') ?: 10;
        $top = Content::selectRaw('subscribe*price as total')->addselect('view_count as count','type','hashid','title','shop_id','price')->orderBy('view_count','desc')->limit($count)->get();
        return $this->output($top);
    }

    public function topShare()
    {
        $count = request('count') ?: 10;
        $top = Content::selectRaw('subscribe*price as total')->addselect('share_count as count','type','hashid','title','shop_id','price')->orderBy('share_count','desc')->limit($count)->get();
        return $this->output($top);
    }
    
}
