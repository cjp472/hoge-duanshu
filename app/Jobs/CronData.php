<?php

namespace App\Jobs;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Models\User;
use App\Models\Shop;
use App\Models\Views;
use App\Models\Order;
use App\Models\UserShop;
use App\Models\VersionOrder;
use App\Models\CronStatistics;

class CronData
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $timeValue;

    public function __construct($data)
    {
        $this->timeValue = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(){
        $timeValue = $this->timeValue;
        $beginYesterday = $timeValue['beginYesterday'];
        $endYesterday = $timeValue['endYesterday'];

        $this->totalShop($beginYesterday,$endYesterday);
        $this->userShop($beginYesterday,$endYesterday);
    }


    private function totalShop($beginYesterday,$endYesterday)
    {
        //新增用户
        $yesterdayUserCount = Shop::whereBetween('create_time',[$beginYesterday,$endYesterday])->count();
        //新增pc端用户
        $yesterdayPcUserCount = Shop::whereBetween('create_time',[$beginYesterday,$endYesterday])->where('channel','desktop')->count();
        //新增移动端用户
        $yesterdayMobileUserCount = Shop::whereBetween('create_time',[$beginYesterday,$endYesterday])->where('channel','mobile')->count();
        //新增付费用户
        $yesterdayPaidUser = VersionOrder::select('shop_id')
            ->whereBetween('success_time',[$beginYesterday,$endYesterday])
            ->where('type','permission')
            ->get();
        $yesterdayPaidUserCount = 0;
        if(!$yesterdayPaidUser->isEmpty()){
            foreach($yesterdayPaidUser as $value){
                $temp[$value->shop_id] = 1;
            }
            $yesterdayPaidUserCount = count(array_keys($temp));
        }
        //昨日总收入
        $income = Order::where('pay_status',1)->whereBetween('pay_time',[$beginYesterday,$endYesterday])->sum('price');
        //新增活跃用户
        $userIds = UserShop::where('admin',1)->pluck('user_id');
        $yesterdayActiveUserCount = User::whereBetween('login_time',[$beginYesterday,$endYesterday])->whereIn('id',$userIds)->count();
        //新增会员
        $yesterdayMemberCount = Member::whereBetween('create_time',[$beginYesterday,$endYesterday])->count();
        //新增付费会员
        $paidMember = Member::select('member.uid')->leftJoin('order','member.uid','order.user_id')
            ->where('order.pay_status','>',0)
            ->whereBetween('order.pay_time',[$beginYesterday,$endYesterday])
            ->groupBy('member.uid')
            ->get();
        $paidMemberCount = count($paidMember);
        //总订单数
        $orderNum = Order::where('pay_status',1)->whereBetween('pay_time',[$beginYesterday,$endYesterday])->count();
        //总阅读数
        $clickNum = Views::whereBetween('view_time',[$beginYesterday,$endYesterday])->count();
        $data = [
            'user' => $yesterdayUserCount,
            'paid_user' => $yesterdayPaidUserCount,
            'active_user' => $yesterdayActiveUserCount,
            'member' => $yesterdayMemberCount,
            'paid_member' => $paidMemberCount,
            'yesterday_income' => $income,
            'order_num' => $orderNum,
            'click_num' => $clickNum,
            'create_time' => $beginYesterday,
            'year' => date('Y',$beginYesterday),
            'month' => date('m',$beginYesterday),
            'day' => date('d',$beginYesterday),
            'shop_id' => 'total',
            'desktop_user' => $yesterdayPcUserCount,
            'mobile_user' => $yesterdayMobileUserCount,
        ];
        //总的
        CronStatistics::insert($data);
    }

    private function userShop($beginYesterday,$endYesterday)
    {
//分店铺
        //店铺新增会员
        $shopMember = Shop::selectRaw('hg_member.shop_id,count(hg_member.shop_id) as number')
            ->leftJoin('member','shop.hashid','member.shop_id')
            ->whereBetween('member.create_time',[$beginYesterday,$endYesterday])
            ->groupBy('member.shop_id')
            ->get();
        $shopMemberData = [];
        if(!$shopMember->isEmpty()){
            foreach($shopMember as $val){
                $shopMemberData[$val->shop_id] = $val->number;
            }
        }

        //店铺新增付费会员
        $shopPaidMember = Shop::select('order.user_id','order.shop_id')
            ->leftJoin('order','shop.hashid','order.shop_id')
            ->where('order.pay_status','>',0)
            ->whereBetween('order.pay_time',[$beginYesterday,$endYesterday])
            ->get();
        $shopPaidMemberData = [];
        if(!$shopPaidMember->isEmpty()){
            foreach($shopPaidMember as $val){
                if(empty($shopPaidMemberData[$val['shop_id']])){
                    $shopPaidMemberData[$val['shop_id']][] = $val['user_id'];
                }else{
                    if(!in_array($val['user_id'],$shopPaidMemberData[$val['shop_id']])){
                        $shopPaidMemberData[$val['shop_id']][] = $val['user_id'];
                    }
                }
            }
            foreach($shopPaidMemberData as $k => $v){
                $shopPaidMemberData[$k] = count($shopPaidMemberData[$k]);
            }
        }

        //店铺付费用户
        $shopPaidUser = VersionOrder::select('shop_id')->whereBetween('success_time',[$beginYesterday,$endYesterday])->where('type','permission')->get();
        $shopPaidUserData = [];
        if(!$shopPaidUser->isEmpty()){
            foreach($shopPaidUser as $value){
                $shopPaidUserData[$value->shop_id] = 1;
            }
        }

        //店铺活跃用户
        $shopActiveUser = UserShop::select('user_shop.shop_id')->leftJoin('users','users.id','user_shop.user_id')
            ->where('user_shop.admin',1)
            ->whereBetween('users.login_time',[$beginYesterday,$endYesterday])
            ->get();
        $shopActiveUserData = [];
        if(!$shopActiveUser->isEmpty()){
            foreach($shopActiveUser as $value){
                $shopActiveUserData[$value->shop_id] = 1;
            }
        }

        //店铺昨日收入
        $shopIncome = Order::select('shop_id','price')->where('pay_status',1)->whereBetween('pay_time',[$beginYesterday,$endYesterday])->get();
        $shopIncomeData = [];
        if(!$shopIncome->isEmpty()){
            foreach($shopIncome as $value){
                if(isset($shopIncomeData[$value->shop_id])){
                    $shopIncomeData[$value->shop_id] += $value->price;
                }else{
                    $shopIncomeData[$value->shop_id] = $value->price;
                }
            }
        }

        //店铺订单数
        $shopOrder = Order::select('shop_id')->where('pay_status',1)->whereBetween('pay_time',[$beginYesterday,$endYesterday])->get();
        $shopOrderData = [];
        if(!$shopOrder->isEmpty()){
            foreach($shopOrder as $value){
                if(isset($shopOrderData[$value->shop_id])){
                    $shopOrderData[$value->shop_id] += 1;
                }else{
                    $shopOrderData[$value->shop_id] = 1;
                }
            }
        }

        //店铺阅读量
        $shopClick = Views::select('shop_id')->whereBetween('view_time',[$beginYesterday,$endYesterday])->get();
        $shopClickData = [];
        if(!$shopClick->isEmpty()){
            foreach($shopClick as $value){
                if(isset($shopClickData[$value->shop_id])){
                    $shopClickData[$value->shop_id] += 1;
                }else{
                    $shopClickData[$value->shop_id] = 1;
                }
            }
        }

        $shop = Shop::select('hashid','create_time')->get();
        if(!$shop->isEmpty()){
            foreach($shop as $value){
                $shopData = [
                    'user' => date('Y-m-d',$value['create_time']) == date('Y-m-d',$beginYesterday) ? 1 : 0,
                    'paid_user' => isset($shopPaidUserData[$value['hashid']]) ? 1 : 0,
                    'active_user' => isset($shopActiveUserData[$value['hashid']]) ? 1 : 0,
                    'member' => isset($shopMemberData[$value['hashid']]) ? $shopMemberData[$value['hashid']] : 0,
                    'paid_member' => isset($shopPaidMemberData[$value['hashid']]) ? $shopPaidMemberData[$value['hashid']] : 0,
                    'yesterday_income' => isset($shopIncomeData[$value['hashid']]) ? $shopIncomeData[$value['hashid']] : 0,
                    'order_num' => isset($shopOrderData[$value['hashid']]) ? $shopOrderData[$value['hashid']] : 0,
                    'click_num' => isset($shopClickData[$value['hashid']]) ? $shopClickData[$value['hashid']] : 0,
                    'create_time' => $beginYesterday,
                    'year' => date('Y',$beginYesterday),
                    'month' => date('m',$beginYesterday),
                    'day' => date('d',$beginYesterday),
                    'shop_id' => $value['hashid']
                ];
                if($shopData['user']>0 || $shopData['paid_user']>0 || $shopData['active_user']>0 || $shopData['member']>0 || $shopData['paid_member']>0 || $shopData['yesterday_income']>0 || $shopData['order_num']>0 || $shopData['click_num']>0) {
                    CronStatistics::insert($shopData);
                }
            }
        }
    }

}
