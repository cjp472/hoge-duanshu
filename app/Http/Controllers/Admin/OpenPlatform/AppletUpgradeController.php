<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/11/22
 * Time: 上午10:04
 */
namespace App\Http\Controllers\Admin\OpenPlatform;

use App\Http\Controllers\Admin\BaseController;
use App\Models\AppletUpgrade;
use App\Models\OpenPlatformApplet;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;

class AppletUpgradeController extends BaseController
{
    /**
     * 小程序升级
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function upgrade()
    {
        $this->validateWithAttribute([
            'appid'   => 'required|alpha_dash',
            'mchid'   => 'required|alpha_dash',
            'api_key' => 'required|alpha_dash'
        ],[
            'appid'   => '小程序id',
            'mchid'   => '商户号',
            'api_key' => '密钥'
        ]);
        $version = Shop::where('hashid',$this->shop['id'])->value('version');
        if ($version == 'basic') {
            return $this->error('no－access-upgrade');
        }
        $applet = OpenPlatformApplet::where(['shop_id'=>$this->shop['id'],'appid'=>request('appid')])->value('id');
        if (!$applet) {
            return $this->error('appid-error');
        }
        $app_id = request('appid');
        $mch_id = request('mchid');
        $api_key = request('api_key');
        $options = config('wechat');
        $options['app_id'] = $app_id;
        $options['payment']['merchant_id'] = $mch_id;
        $options['payment']['key'] = $api_key;
        $app = new \EasyWeChat\Foundation\Application($options);
        $payment = $app->payment;
        //调用查询订单接口测试提交的信息是否正确
        $result = $payment->query('test_order_id');
        //如果查询接口返回正常 则提交信息正确
        if($result && isset($result['return_code']) && $result['return_code'] == 'SUCCESS'){
            $is_app = AppletUpgrade::where('shop_id',$this->shop['id'])->first();
            if ($is_app) {
                $is_app->appid = request('appid');
                $is_app->mchid = request('mchid');
                $is_app->api_key = request('api_key');
                $is_app->saveOrFail();
            } else {
                $app = new AppletUpgrade();
                $app->shop_id = $this->shop['id'];
                $app->appid = request('appid');
                $app->mchid = request('mchid');
                $app->api_key = request('api_key');
                $app->create_time = time();
                $app->saveOrFail();
            }
            Shop::where('hashid',$this->shop['id'])->update(['applet_version'=>'advanced']);
            return $this->output(['success'=>1]);
        }else {
            return $this->error('mch-info-error');
        }
    }

    /**
     * 小程序详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function appletDetail()
    {
        $applet = AppletUpgrade::where('shop_id',$this->shop['id'])->first() ? : [];
        if ($applet) {
            $applet->create_time = hg_format_date($applet->create_time);
        }
        return $this->output($applet);
    }

    /**
     * 小程序降级
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function downgrade()
    {
//        Shop::where('hashid',$this->shop['id'])->update(['applet_version'=>'basic']);
        $applet = OpenPlatformApplet::where('shop_id', $this->shop['id'])->first();
        if (!$applet) {
            return $this->error('applet-not-found');
        }
        AppletUpgrade::where('shop_id',$this->shop['id'])->delete();
        Shop::where('hashid',$this->shop['id'])->update(['applet_version'=>'basic']);
        return $this->output(['success'=>1]);
    }
}