<?php
namespace App\Http\Controllers\Admin\Setting;

use App\Models\Shop;
use App\Models\MessageRecord;
use App\Http\Controllers\Admin\BaseController;

class MessageController extends BaseController
{
    const PAGINATE = 20;
    /**
     * 统计
    */
    public function statistics()
    {
        $shopId = $this->shop['id'];
        $message = Shop::where('hashid',$shopId)->value('message');
        $time = time();
        //当天
        $todayTime = [
            strtotime(date('Y-m-d'),$time),
            $time
        ];
        //近一周
        $weekTime = [
            strtotime('-7 days'),
            $time
        ];
        //本月
        $monthTime = [
            mktime(0,0,0,date('m'),1,date('Y')),
            $time
        ];
        $data = [
            'today' => $this->calculate($shopId,$todayTime),
            'week'  => $this->calculate($shopId,$weekTime),
            'month' => $this->calculate($shopId,$monthTime),
            'total' => $this->calculate($shopId,'',false),
            'message' => intval($message)
        ];
        return $this->output($data);
    }

    private function calculate($shopId,$time,$flag = true)
    {
        $obj = MessageRecord::where(['shop_id'=>$shopId,'sms_type'=>1,'type'=>1]);
        if($flag){
            $obj = $obj->whereBetween('create_time',$time);
        }
        return $obj->count();
    }

    /**
     * 明细
    */
    public function detail()
    {
        $shopId = $this->shop['id'];
        $type = request('type');
        $time = time();
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $times = [];
        $plan = request('plan');
        switch($type){
            case 'day':
                $times = [
                    strtotime(date('Y-m-d'),$time),
                    $time
                ];
                break;
            case 'week':
                $times = [
                    strtotime('-7 days'),
                    $time
                ];
                break;
            case 'month':
                $times = [
                    strtotime('-30 days'),
                    $time
                ];
                break;
        }
        $obj = MessageRecord::where('shop_id',$shopId);
        if($times){
            $obj = $obj->whereBetween('create_time',$times);
        }
        if($plan){
            $obj = $obj->where('type',$plan);
        }
        $lists = $obj->orderBy('create_time','desc')->paginate($count);
        $messageLists = $this->listToPage($lists);
        return $this->output($messageLists);
    }
}