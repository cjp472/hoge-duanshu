<?php

namespace App\Http\Controllers\Manage\Customer;


use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Customer;
use App\Models\Manage\CustomerFollow;
use Illuminate\Support\Facades\Auth;

class FollowController extends BaseController
{
    //跟进信息
    public function followLog()
    {

        $count = request('count') ?: 10;
        $sql = CustomerFollow::select('*');
        if($shop_id = request('shop_id')){
            $sql->where('shop_id',$shop_id);
        }
        if( !Auth::user()->hasRole('master')){
            $sql->where('user_id',$this->user['id']);
        }
        $list = $sql->orderBy('date','desc')
            ->paginate($count);
        if($list){
            foreach ($list->items() as $item){
                $item->is_edit = $item->date < strtotime(date('Ymd')) ? 0 : 1;
                $item->date = $item->date ? date('Y-m-d H:i:s',$item->date) : '-';
            }
        }
        return $this->output($this->listToPage($list));
    }

    //添加跟进日志
    public function addFollowLog()
    {
        $date = request('date') ? strtotime(request('date')) : time();
        $shop_id = request('shop_id');
        $cf = new CustomerFollow();
        $cf->date = $date;
        $cf->shop_id = $shop_id;
        $cf->user_id = $this->user['id'];
        $cf->follow_user = $this->user['name'];
        $cf->content = request('content');
        $cf->save();
        $cf->date = date('Y-m-d H:i:s',$cf->date);
        $cf->is_edit = 1;
        return $this->output($cf);
    }

    //更新跟进日志
    public function updateFollowLog()
    {
        $id = request('id');
        $cf = CustomerFollow::where(['id' => $id])->first();
        if(request('date')){
            $cf->date = strtotime(request('date'));
        }
        $cf->content = request('content');
        $cf->save();
        return $this->output(['success'=>1]);
    }

    //删除跟进日志
    public function deleteFollwLog()
    {
        $id = request('id');
        CustomerFollow::where(['id' => $id])->delete();
        return $this->output(['success'=>1]);
    }

    //预约下次跟进时间
    public function bookFollowTime()
    {
        $shop_id = request('shop_id');
        $data = strtotime(request('date'));
        Customer::where('shop_id',$shop_id)
            ->where('user_id',$this->user['id'])
            ->update(['follow_date'=>$data]);
        return $this->output(['success'=>1]);
    }
}