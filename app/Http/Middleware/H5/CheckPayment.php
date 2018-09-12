<?php

namespace App\Http\Middleware\H5;

use App\Models\Alive;
use App\Models\CardRecord;
use App\Models\Column;
use App\Models\Content;
use App\Models\Member;
use App\Models\Payment;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CheckPayment
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
                'error'     => 'no-content-id',
                'message'   => trans('validation.required',['attribute'=>'内容id']),
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


    private function checkPay($content_id,$member_id,$shop_id,$member)
    {
        //设置的有权限的运营人员可直接查看
        if(Redis::sismember('auth:member',$member['openid'])){
            return 1;
        }

        $content = Content::where(['hashid'=>$content_id,'shop_id'=>$shop_id])->firstOrFail();
        //试看内容
        if($content->is_test){
            return 1;
        }
        switch (intval($content->payment_type)){
            //专栏调整，兼容老数据专栏外单卖和专栏相同判断处理，
            case 1: //专栏
            case 4: //专栏外单卖
                if ($content->column_id && $this->checkColumnPayment($member_id,$shop_id,$content->column_id)){ // column_id pk
                    return 1;
                }
                break;
            case 2: //收费
                if($this->checkPayment($member_id,$shop_id,$content_id,$content->type,$content->join_membercard)){
                    return 1;
                }
                break;
            case 3: //免费
                return 1;
                break;
            default :
                if($this->checkPayment($member_id,$shop_id,$content_id,$content->type,$content->join_membercard)){
                    return 1;
                }
                break;
        }
    }

    private function checkPayment($member_id,$shop_id,$content_id,$type,$join_membercard)
    {
        $pay = Payment::checkProductPayment($shop_id, $member_id, $type, $content_id) || ($join_membercard && Member::hasFreeMemberCard($member_id,$shop_id));
        $lecturer = 0;
        $type == 'live' && $lecturer = $this->checkLecturer($content_id,$member_id);
        if($pay || $lecturer){
            return 1;
        }else{
            return 0;
        }
    }

    private function checkLecturer($content_id,$member_id){
        $alive = Alive::where('content_id',$content_id)->firstOrFail();
        $person_id = array_pluck(json_decode($alive->live_person, true),'id');
        $lecturer = in_array($member_id,$person_id) ? 1 : 0;
        return $lecturer;
    }

    private function checkColumnPayment($member_id,$shop_id,$column_id)
    {
        $column = Column::find($column_id);
        return Payment::checkProductPayment($shop_id,$member_id,'column',$column->hashid) || Member::hasFreeMemberCard($member_id,$shop_id);
    }
}