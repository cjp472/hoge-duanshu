<?php

namespace App\Http\Middleware\H5;


use App\Models\CardRecord;
use App\Models\ClassContent;
use App\Models\Course;
use App\Models\Member;
use App\Models\Payment;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CheckCoursePayment
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

        $id = $request->route('id') ? : $request->course_id;
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
        //如果是免费课时直接返回
        if($request->class_id){
            $class = ClassContent::where(['shop_id'=>$shop_id,'course_id'=>request('course_id')])
                ->findOrFail(request('class_id'));
            if($class->is_free == 1) {
                return $next($request);
            }

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

    private function checkPay($course_id,$member_id,$shop_id,$member)
    {
        //设置的有权限的运营人员可直接查看
        if(Redis::sismember('auth:member',$member['openid'])){
            return 1;
        }

        $content = Course::where(['hashid'=>$course_id,'shop_id'=>$shop_id])->firstOrFail();
        if($content->price == 0){
            return 1;
        }

        if(Payment::checkProductPayment($shop_id,$member_id,'course',$course_id)){
            return 1;
        }

        if($content->join_membercard && Member::hasFreeMemberCard($member_id,$shop_id)){
            return 1;
        }

        return 0;
    }

}