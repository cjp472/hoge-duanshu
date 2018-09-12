<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 18/1/23
 * Time: 上午9:15
 */
namespace App\Http\Controllers\Manage\Shop;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\MessageRecord;
use App\Models\Manage\UserShop;
use App\Models\Manage\VersionOrder;
use App\Models\Shop;

class MessageController extends BaseController
{
    /**
     * 短信服务列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function lists()
    {
        $this->validateWith([
            'count'      => 'numeric',
            'start_time' => 'date',
            'end_time'   => 'date',
            'title'      => 'alpha_dash'
        ]);
        $count = request('count') ? : 15;
        $data = VersionOrder::select('version_order.*','shop.title')
            ->where('version_order.type','message')
            ->join('shop','shop.hashid','=','version_order.shop_id');
        request('start_time') && request('end_time') && $data->whereBetween('version_order.success_time',[request('start_time'),request('end_time')]);
        request('start_time') && !request('end_time') && $data->whereBetween('version_order.success_time',[request('start_time'),time()]);
        request('end_time') && !request('start_time') && $data->whereBetween('version_order.success_time',[0,request('end_time')]);
        request('title') && $data->where('shop.title','like','%'.request('title').'%');
        $return = $data->paginate($count);
        if ($return->items()) {
            foreach ($return->items() as $item) {
                $item->success_time = $item->success_time ? hg_format_date($item->success_time) : 0;
            }
        }
        return $this->output($this->listToPage($return));
    }

    /**
     * 用户管理下的短信包
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userMessage()
    {
        $this->validateWith([
            'count'    => 'numeric',
            'id'    => 'numeric'
        ]);
        $shop = UserShop::where('user_id',request('id'))->value('shop_id');
        if(!$shop){
            $this->error('no_shop');
        }
        $count = request('count') ? : 15;
        $return = MessageRecord::where('shop_id',$shop)->paginate($count);
        if ($return->items()) {
            foreach ($return->items() as $item) {
                $item->create_time = $item->create_time ? hg_format_date($item->create_time) : 0;
            }
        }
        $ret = $this->listToPage($return);
        $ret['msg'] = Shop::where('hashid',$shop)->value('message');
        return $this->output($ret);
    }
}
