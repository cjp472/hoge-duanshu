<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/7/19
 * Time: 15:12
 */

namespace App\Http\Controllers\Sms\Aliyun;

use App\Models\Shop;
use App\Events\SystemEvent;
use App\Models\MessageRecord;
use App\Events\CurlLogsEvent;
use App\Http\Controllers\Sms\BaseController;
use App\Models\ShopSmsTemplate;
use DefaultProfile;
use DefaultAcsClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Dysmsapi\Request\V20170525\SendSmsRequest;

class SmsController extends BaseController
{

    /**
     * 使用阿里云服务发送短信
     * @return \Illuminate\Http\JsonResponse
     */
    public function send(){

        $this->validateWithAttribute([
            'mobile'    => 'required|regex:/^1[35678][0-9]{9}(,1[35678][0-9]{9})*$/',
            'type'      => 'required|alpha|in:verify,notice',
            'code'      => 'required|numeric'
        ],[
            'mobile'    => '手机号',
            'type'      => '发送类型',
            'code'      => '验证码',
        ]);
        $config = config('sms.aliyun');
        $profile = DefaultProfile::getProfile($config['region'], $config['accessKeyId'], $config['accessKeySecret']);
        DefaultProfile::addEndpoint("cn-hangzhou", "cn-hangzhou", $config['product'], $config['domain']);
        $acsClient= new DefaultAcsClient($profile);
        $request = new SendSmsRequest;
        $request->setPhoneNumbers(request('mobile'));
        $request->setSignName($config['signName']);
        $request->setTemplateCode($config['templateCode'][request('type')]['template_id']);
        $request->setTemplateParam($this->replaceTemplate($config));
        //选填-发送短信流水号
//        $request->setOutId();
        //发起访问请求
        $acsResponse = $acsClient->getAcsResponse($request);
        if($acsResponse->Code != 'OK'){
            $this->errorWithText($acsResponse->Code,$acsResponse->Message);
        }
        return $this->output(['success'=>1]);

    }

    /**
     * 替换短信模板参数
     * @param $config
     * @return string
     */
    private function replaceTemplate($config){
        $param = [];
        if($config['templateCode'][request('type')]['param']){
            foreach ($config['templateCode'][request('type')]['param'] as $key=>$item) {
                $param[$key] = request($key);
            }
        }
        return $param ? json_encode($param) : '';
    }

    /**
     * 发送手机验证码
     * @return \Illuminate\Http\JsonResponse
     */
    public function mobileCode(){
        $this->validateWithAttribute([
            'mobile'    => 'required|regex:/^1[3,5,6,7,8,9]\d{9}(,1[3,5,6,7,8,9]\d{9})*$/',
            'shop_id'   => 'alpha_dash'
        ]);

        if(!request()->header('x-device-id') && (!request('captcha') || !$this->checkCaptcha(request('captcha')))){
            $this->error('captcha-error');
        }
        //校验店铺短信余额
        request('shop_id') && $shop = $this->checkShopMessage();

        //发送验证码
        $this->sendCode();

        //店铺短信余额处理
        request('shop_id') && $this->saveMessageSendRecord($shop);

        return $this->output(['success' => 1,'count' => 0]); // count 兼容以前数据保留，表示已发送次数
    }

    /**
     * 验证店铺短信余额
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    private function checkShopMessage(){
        $shop = Shop::where('hashid',request('shop_id'))->first();
        if(!$shop){
            $this->error('no-shop');
        }
        if($shop->version == VERSION_BASIC && $shop->message <= 0){
            event(new SystemEvent(request('shop_id'),'短信余额不足','短信余额已不足,请尽快充值!',0,-1,'系统管理员'));
            $this->error('message_no_margin');
        }
        return $shop;
    }

    /**
     * 发送短信处理
     */
    private function sendCode(){
        $verify_code = $this->generateMobileCode();
//        $param = [
//            'code'  => $verify_code,
//            'type'  => 'verify',
//            'mobile'    => request('mobile'),
//        ];
//        $this->sendMobileCode($param,config('sms.api.aliyun_send'));

        //设置默认模板标识
        $slug = "duanshu-code";
        $shop_id = request('shop_id');
        $param = [
            "template" => $slug,
            "kwargs" => [
                'code' => $verify_code
            ],
            "target" => [request('mobile')]
        ];
        //判断店铺是否开启了隐藏版权信息

        if($shop_id && ($shop = Shop::where('hashid',$shop_id)
                ->where('copyright',0)
                ->select(['title'])->first())){
            //判断是否设置自己的短信模板
//            $sst = ShopSmsTemplate::where('shop_id',$shop_id)->value('template_slug');
//            if( $sst ) {
//                //使用用户自己的短信模板（特殊要求:模板的变量需要与短书必须保持一致）;
//                $slug = $sst;
//            }
            $slug = "duanshu-sign-code";
            $param['template'] = $slug;
            $param['kwargs']['sign'] = $shop->title;
        }
        $this->sendMobileCodeByStore($param,config('define.service_store.api.sms'));
    }

    /**
     * 保存发送记录信息
     * @param $shop
     */
    private function saveMessageSendRecord($shop){
        try{
            MessageRecord::insert([
                'shop_id' => request('shop_id'),
                'user' => request('mobile'),
                'type' => 1,
                'number' => -1,
                'create_time' => time()
            ]);
            //调整为基础版扣除短信余额
            if($shop->version == VERSION_BASIC){
                $shop->decrement('message',1);
                if($shop->message > 0 && $shop->message < 50){
                    event(new SystemEvent(request('shop_id'),'短信余额不足','短信余额已不足50,请尽快充值!',0,-1,'系统管理员'));
                }elseif($shop->message <= 0){
                    $hash = 'announce:'.request('shop_id');
                    Redis::hset($hash,'message',0);
                    event(new SystemEvent(request('shop_id'),'短信余额充值提醒','您的店铺短信余量已为0，为了避免影响用户正常使用店铺功能，请及时到用户中心短信服务进行短信包充值。',0,-1,'系统管理员'));
                }
            }
        }catch(\Exception $e){
            $this->error($e->getMessage());
        }
    }

    public function checkCaptcha($captcha)
    {
        $cacheCaptcha = Redis::lrange('captcha',0,-1);
        $captcha = strtolower($captcha);
        if(in_array($captcha,$cacheCaptcha)){
            Redis::lrem('captcha',0,$captcha);
            return true;
        }
        return false;
    }

}