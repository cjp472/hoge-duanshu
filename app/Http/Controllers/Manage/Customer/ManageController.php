<?php
namespace App\Http\Controllers\Manage\Customer;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Customer;
use App\Models\Manage\CustomerFollow;
use App\Models\Manage\Shop;

class ManageController extends BaseController
{
    //指派客户到某一销售人员下
    public function changeCustomerFollow()
    {
        $user_id = request('user_id');
        $shop_id = request('shop_id');
        $cus = Customer::where([
            'shop_id' => $shop_id
        ])->first();
        if($user_id && !$cus->cooperation){
            $count = Customer::where([
                'user_id'=>$user_id,
                'cooperation'=>0])->count();
            if($count >= 200){
                $this->error('over_num',['num'=>200]);
            }
        }
        Customer::where('shop_id',$shop_id)
            ->update(['user_id'=>$user_id]);

        CustomerFollow::where('shop_id',$cus->shop_id)
            ->update(['user_id'=>$user_id]);
        return $this->output(['success'=>1]);
    }

    //添加新客户近公有池
    public function addNewToPublic()
    {
        $cus = new Customer();
        $cus->user_name = request('user_name');
        $cus->contacts = request('contacts');
        $cus->telephone = request('telephone');
        $cus->intention = request('intention');
        $cus->save();
        return $this->output(['success'=>1]);
    }
}