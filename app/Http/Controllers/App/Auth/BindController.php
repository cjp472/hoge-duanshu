<?php

namespace app\Http\Controllers\App\Auth;

use App\Http\Controllers\App\InitController;
use App\Models\Member;
use App\Models\MemberBind;

class BindController extends InitController
{

    public function memberBind(){
        $this->validateWithAttribute(['mobile' =>'required'],['mobile'=>'手机号']);
        $uid = md5(request('shop_id').'app'.request('mobile'));
        $union_id = $this->checkMobile($uid);
        $this->formatBind($uid,$union_id);
        return $this->output(['success'=>1]);
    }

    private function formatBind($uid,$union_id){
        $bind_str = md5(request('shop_id').request('mobile').$union_id);
        MemberBind::insert(
            ['uid'=>$this->member['id'],'union_mobile'=>$bind_str],
            ['uid'=>$uid,'union_mobile'=>$bind_str]);
    }

    private function checkMobile($uid){
        $mobile = Member::where('uid',$uid)->first();
        if($mobile){
            return Member::where('uid',$this->member['id'])->value('union_id');
        }else{
            return $this->error('no_register');
        }
    }


}