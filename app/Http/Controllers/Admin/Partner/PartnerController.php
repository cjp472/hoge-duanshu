<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 17/5/10
 * Time: 11:47
 */
namespace App\Http\Controllers\Admin\Partner;


use App\Http\Controllers\Admin\BaseController;
use App\Models\PartnerApply;
use App\Models\Shop;
use App\Models\UserButtonClicks;
use App\Models\UserShop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class PartnerController extends BaseController{

    /**
     * @return mixed
     * 合伙人申请
     */
    public function applyPartner(){
        $this->validateApply();
        $this->verifyCode();
        $data = $this->createApply();
        return $this->output($data);
    }

    private function createApply(){
        $apply = new PartnerApply();
        $apply->company_name = request('company_name');
        $apply->company_email = request('company_email');
        $apply->contacts = request('contacts');
        $apply->mobile = request('mobile');
        $apply->address = request('address');
        $apply->apply_time = time();
        $apply->type = request('type')?:'partner';
        $apply->saveOrFail();
        //删除改店铺点击高级版升级按钮存储的浏览记录，
        request('shop_id') && UserButtonClicks::where('shop_id',request('shop_id'))->where('type','fullplat')->delete();
        return $apply;
    }

    private function verifyCode(){
        $data = PartnerApply::where('mobile',request('mobile'))->first();
        $data && $this->error('mobile_used');
        $code = Cache::get('mobile:code:'.request('mobile'));
        !$code && $this->error('code_is_overtime');
        $code != request('code') && $this->error('error_code');
    }

    private function validateApply(){
        $this->validateWithAttribute([
            'company_name' => 'required|max:64',
            'company_email' => 'required|email',
            'contacts' => 'required',
            'mobile' => 'required|regex:/^(\+?86-?)?1[8,5,3,7][0-9]{9}$/',
            'code' => 'required',
            'address' => 'required',
            'type' => 'required',
        ],[
            'company_name' => '企业名称',
            'company_email' => '企业邮箱',
            'contacts' => '联系人',
            'mobile' => '手机号',
            'code' => '验证码',
            'address' => '地址',
            'type' => '申请类型',
        ]);
    }

    /**
     * 激活合伙人
     */
    public function partnerActive()
    {
        $this->validateWithAttribute(['agree' => 'required|numeric|size:1'], ['agree' => '是否同意协议']);
        $shop = Shop::where(['hashid' => $this->shop['id']])->firstOrFail();
        if ($shop && $shop->version = 'unactive-partner') {
            $shop->version = 'partner';
            $shop->save();
            $userShop = UserShop::where('shop_id', $this->shop['id'])->get();
            if ($userShop) {
                foreach ($userShop as $v) {
                    $shopResponse = [
                        'id' => $v->shop_id,
                        'version' => 'partner',
                        'admin' => $v->admin ? 1 : 0,
                        'permission' => $v->permission ? unserialize($v->permission) : [],
                    ];
                    $sessions['shop:' . $v->user_id] = $shopResponse;
                }
                $sessions && Session::put($sessions);
                Cache::forget('share:' . $this->shop['id']);
                return $this->output(['success' => 1]);
            } else {
                return $this->error('no_apply');
            }
        } elseif ($shop && $shop->version == 'partner') {
            return $this->error('partner_already');
        } else {
            return $this->error('no-shop');
        }
    }
}
