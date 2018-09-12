<?php

namespace App\Listeners;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\SystemEvent;
use App\Models\Manage\Customer;
use App\Models\Shop;
use App\Models\ShopClose;
use App\Models\ShopDisable;
use App\Models\ShopFundsArrears;
use App\Models\ShopStorageFluxFree;
use App\Models\UserShop;
use App\Models\VersionExpire;
use GuzzleHttp\Client;
use App\Events\Registered;
use Illuminate\Database\QueryException;
use Vinkla\Hashids\Facades\Hashids;

class RegisterShop
{
    public function handle(Registered $event)
    {
        $shop = $this->createShop($event->user,[
            'channel' => $event->channel,
            'agent'     => $event->agent,
        ]);
        $shop_id = $shop->hashid;
        $this->createUserShop($event->user->id,$shop_id);
        $this->createShopVersion($shop_id);
        $this->Customer($shop_id,$event->user);
        event(new SystemEvent($shop_id,'认证提醒',trans('notice.content.verify.not'),0,-1,'系统管理员',1));
        $this->giftStorageFlux($shop_id);
        $this->postRegister($shop);
    }

    protected function createShopVersion($shop_id){
        $version = new VersionExpire();
        $version->hashid = $shop_id;
        $version->version = 'standard';
        $version->start = time();
        $version->expire = strtotime('+7day',time());
        $version->is_expire = 0;
        $version->method = 0;
        $version->saveOrFail();
    }

    protected function createShop($user,$channel)
    {
        $shop = new Shop();
        $shop->title = $user->name.'的店铺';
        $shop->brief = '店铺的描述';
        $shop->create_time = time();
        $shop->version = 'standard';
        $shop->channel = $channel['channel'];
        $shop->agent = $channel['agent'];
        $this->saveShop($shop);
        $shop->hashid = Hashids::connection('shop')->encode($shop->id);
        $shop->save();
        return $shop;
    }

    protected function saveShop($shop)
    {
        try {
            $shop->h5_host = get_random_string('5') . '.duanshu.com';
            $shop->save();
        } catch (QueryException $e) {
            $errorInfo = $e->errorInfo;
            if ($errorInfo[1] == 1062 && strstr($errorInfo[2], 'shop_h5_host_unique')) {
                $this->saveShop($shop);
            }
        }
    }

    protected function createUserShop($user_id,$shop_id)
    {
        $userShop = new UserShop();
        $userShop->user_id = $user_id;
        $userShop->shop_id = $shop_id;
        $userShop->admin = 1;
        $userShop->saveOrFail();
    }

    protected function create_merchant(Shop $shop)
    {
        $param = [
            'user_id' => $shop->hashid,
        ];
        $signature = hg_pay_signature($param);
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-signature' => $signature['sign'],
                'x-api-key' => $signature['access_key'],
                'x-api-timestamp' => $signature['timestamp'],
            ],
            'body'  => json_encode($param),
        ]);
        $res = $client->request('POST',config('define.pay.plat.register_mch'));
        $ret = $res->getBody()->getContents();
        event(new CurlLogsEvent($ret,$client,config('define.pay.plat.register_mch')));
        if($ret){
            $return = json_decode($ret);
            if($return && $return->result && $return->result->mch_id){
                $shop->mch_id = $return->result->mch_id;
                $shop->save();
            }elseif($return && $return->error_code == 30001){
                $shop->mch_id = 200000000145;
                $shop->save();
            }
        }
    }

    public function Customer($id, $user)
    {
        $cus = new Customer();
        $cus->shop_id = $id;
        $cus->user_name = $user->name.'的店铺';
        $cus->customer_id = $user->id;
        if( $user && isset($user->mobile)){
            $cus->telephone = $user->mobile;
        }
        $cus->save();
    }

    private function giftStorageFlux($shop_id){
        if($shop_id){
            $now = time();
            $start_time = strtotime(date('Y-m-01', $now));
            $end_time = strtotime('1 months', $start_time);
            if(!ShopDisable::isShopDisable($shop_id) && !ShopFundsArrears::isFundsArrears($shop_id)){
                ShopStorageFluxFree::createStorageFluxFree($shop_id, QCOUND_COS, DEFAULT_BASE_STORAGE, $start_time, $end_time);
                ShopStorageFluxFree::createStorageFluxFree($shop_id, QCOUND_CDN, DEFAULT_BASE_FLOW, $start_time, $end_time);
            }
        }
    }

    private function postRegister($shop)
    {
        $id = $shop->id;
        $shop_id = $shop->hashid;
        try {
            $timestamp = time();
            $param = ['shop' => $id];
            $app_id = config('define.inner_config.sign.key');
            $app_secret = config('define.inner_config.sign.secret');
            $client = hg_verify_signature($param, $timestamp, $app_id, $app_secret, $shop_id);
            $url = config('define.python_duanshu.api.internal_register');
            $res = $client->request('POST', $url);
            $response = $res->getBody()->getContents();
            event(new CurlLogsEvent($response, $client, $url));
        } catch (\Exception $exception) {
            event(new ErrorHandle($exception, 'notify python register'));
        }
    }

}