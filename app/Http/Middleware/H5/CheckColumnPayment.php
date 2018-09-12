<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/2/12
 * Time: 13:43
 */

namespace App\Http\Middleware\H5;

use App\Models\CardRecord;
use App\Models\Column;
use App\Models\Member;
use App\Models\Payment;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;


class CheckColumnPayment
{

    public function handle($request, Closure $next)
    {
        $sign = $request->memberInfo;
        if(!$sign){
            return response([
                'error'     => 'no-member-info',
                'message'   => trans('validation.no-member-info'),
            ]);
        }
        $member_id = $sign['id'];
        if(!$member_id){
            return response([
                'error'     => 'no-member-info',
                'message'   => trans('validation.required',['attribute'=>'会员id']),
            ]);
        }

        $id = $request->route('id');
        if(!$id){
            return response([
                'error'     => 'no-course-id',
                'message'   => trans('validation.required',['attribute'=>'课程id']),
            ]);
        }

        $shop_id = $request->shop_id;
        if(!$shop_id){
            return response([
                'error'     => 'no-shop-id',
                'message'   => trans('validation.required',['attribute'=>'店铺id']),
            ]);
        }

        if(!$this->checkPay($id,$member_id,$shop_id,$sign)){
            return response([
                'error'     => 'no-pay',
                'message'   => trans('validation.no-pay'),
            ]);
        }
        //执行操作
        return $next($request);
    }

    private function checkPay($column_id,$member_id,$shop_id,$member)
    {
        //设置的有权限的运营人员可直接查看
        if(Redis::sismember('auth:member',$member['openid'])){
            return 1;
        }
        
        $content = Column::where(['hashid'=>$column_id,'shop_id'=>$shop_id])->firstOrFail();
        if($content->price == 0){
            return 1;
        }

        if($content->join_membercard && Payment::checkProductPayment($shop_id,$member_id,'column',$column_id)){
            return 1;
        }

        if(Member::hasFreeMemberCard($member_id,$shop_id)){
            return 1;
        }
        return 0;
    }

}