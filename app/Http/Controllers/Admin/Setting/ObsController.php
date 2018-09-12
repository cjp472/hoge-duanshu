<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2018/4/3
 * Time: 15:28
 */

namespace App\Http\Controllers\Admin\Setting;


use App\Http\Controllers\Admin\BaseController;
use App\Models\Alive;
use App\Models\ModulesShopModule;
use App\Models\ObsReply;
use App\Models\Shop;
use App\Models\ShopM20Member;
use App\Models\ShopObs;
use App\Models\UserShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ObsController extends BaseController
{

    /**
     * 开通obs直播功能
     */
    public function openOBS(){

        $this->shop['id'] = request('shop_id') ? : $this->shop['id'];
        if(request('close')){
            //关闭在线直播
            Shop::where(['hashid' => $this->shop['id']])->update(['is_obs'=>0]);
            $userShop = UserShop::where('shop_id', $this->shop['id'])->get();
            if ($userShop) {
                foreach ($userShop as $v) {
                    Cache::forever('change:'.$v->user_id,1);
                }
            }
            return $this->output(['success'=>1]);
        }
        $shop_id = $this->shop['id'];
        $shop = Shop::where(['hashid' => $shop_id])->firstOrFail();
        $is_live_open = ModulesShopModule::isModuleOpen($shop->id, ModulesShopModule::MODULE_SLUG_OBS_LIVE);
        if (!$is_live_open) {
            $this->error('not-open-obs');
        }
        $shop_obs = ShopObs::where(['shop_id' => $this->shop['id'],'type'=> 'obs'])->first();
        $key = 'shop:obs:refresh:' . $this->shop['id'];
        $time = 60 * 60;
        if (!Redis::set($key, 1, "nx", "ex", $time)) {
            return $this->error('obs-refresh-too-fast');
        }
        $shop_obs = $this->saveObsInfo($shop_obs,1,'ds_obs_'.$this->shop['id'].'_room1', '', $this->shop['id']);
        $userShop = UserShop::where('shop_id', $this->shop['id'])->get();
        Shop::where(['hashid' => $this->shop['id']])->update(['is_obs'=>1]);
        if ($userShop) {
            foreach ($userShop as $v) {
                Cache::forever('change:'.$v->user_id,1);
            }
        }
        return $this->output($shop_obs);

        //数据入库

    }

    /**
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response|void
     */
    public function refreshObs()
    {
        $shop_id = request('shop_id') ?: $this->shop['id'];
        $shop = Shop::where(['hashid' => $shop_id])->first();
        if (!$shop) {
            return response([
                'error' => 'shop-not-found',
                'message' => trans('validation.shop-does-not-exist'),
            ]);
        }
        $is_obs_live_open = ModulesShopModule::isModuleOpen($shop->id, ModulesShopModule::MODULE_SLUG_OBS_LIVE);
        if (!$is_obs_live_open) {
            $this->error('module-obs-live-not-open');
        }
        $key = 'shop:obs:refresh:' . $this->shop['id'];
        $time = 60 * 60;
        if (!Redis::set($key, 1, "nx", "ex", $time)) {
            return $this->error('obs-refresh-too-fast');
        }
        $shop_obs = ShopObs::where(['shop_id' => $this->shop['id'], 'type' => 'obs'])->first();
        $shop_obs = $this->saveObsInfo($shop_obs, 1, 'ds_obs_' . $this->shop['id'] . '_room1','', $shop_id);
        return $this->output($shop_obs);
    }

    /**
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function openLive()
    {
        $shop_id = request('shop_id');
        $slug = request('slug');
        $shop = Shop::where(['hashid' => $shop_id])->first();
        if (!$shop) {
            return response([
                'error' => 'shop-not-found:'.$shop_id,
                'message' => trans('validation.shop-does-not-exist'),
            ]);
        }
//        $is_live_open = ModulesShopModule::isModuleOpen($shop->id, $slug);
//        if (!$is_live_open) {
//            $this->error('module-live-not-open');
//        }
        $type = $slug == ModulesShopModule::MODULE_SLUG_OBS_LIVE? 'obs':'online';
        $is_obs = $slug == ModulesShopModule::MODULE_SLUG_OBS_LIVE ? 1 : 0;
        $shop_obs = ShopObs::where(['shop_id' => $shop_id, 'type' => $type])->first();
        if($is_obs){
            $shop_obs = $this->saveObsInfo($shop_obs, $is_obs, 'ds_obs_' . $shop_id . '_room1', '', $shop_id);
        } else {
            $m2o_config = config('define.M2O');
            $user_id = 'ds'.random_int(10000000,99999999);
            $member_info = $this->getM2oMember($m2o_config,$user_id, $shop_id);
            $extra = json_decode($member_info['member_extra'],1);
            $shop_obs = $this->saveObsInfo($shop_obs,$is_obs,$member_info['member_id'],$extra['access_token'], $shop_id);
            $shop_obs->member_name = $member_info->member_name;
            $shop_obs->password = $member_info->password;
        }
        return $this->output($shop_obs);
    }


    private function saveObsInfo($shop_obs,$is_obs=1,$user_id='',$token = '', $shop_id){
        $param = [
            'user_id'    => $user_id ,
            'access_token' => $token
        ];
        $url = config('define.M2O.api.obs_info');

        $response = $this->curlClient($param,$url);
        if($response['error_code']){
            $this->errorWithText($response['error_code'],$response['error_message']);
        }
        if($response['result']) {
            $result = $response['result'];
            if (!$shop_obs) {
                $shop_obs = new ShopObs();

                $shop_obs->setRawAttributes([
                    'shop_id' => $shop_id,
                    'stream_id' => isset($result['stream_id']) ? $result['stream_id'] : '',
                    'push_url' => isset($result['push_url_base']) ? $result['push_url_base'] : '',
                    'expire_time' => isset($result['expiry_date']) ? strtotime($result['expiry_date']) : 0,
                    'play_url' => isset($result['hls_play_url']) ? $result['hls_play_url'] : '',
                    'push_secret' => isset($result['push_url_extend']) ? $result['push_url_extend'] : '',
                    'type' => $is_obs ? 'obs' : 'online',
                ]);
                $is_obs && Shop::where(['hashid' => $shop_id])->update(['is_obs' => 1]);
            } else {
                $shop_obs->push_url = isset($result['push_url_base']) ? $result['push_url_base'] : '';
                $shop_obs->play_url = isset($result['hls_play_url']) ? $result['hls_play_url'] : '';
                $shop_obs->stream_id = isset($result['stream_id']) ? $result['stream_id'] : '';
                $shop_obs->push_secret = isset($result['push_url_extend']) ? $result['push_url_extend'] : '';
                $shop_obs->expire_time = strtotime($result['expiry_date']);
                $shop_obs->type = $is_obs ? 'obs' : 'online';
            }
            $shop_obs->save();
            $shop_obs->expire_time = $shop_obs->expire_time ? hg_format_date($shop_obs->expire_time) : '';
        }
        return $shop_obs;
    }


    /**
     * 获取obs直播信息
     */
    public function obsInfo(){
        $shop_id = $this->shop['id'];
        $shop = Shop::where(['hashid' => $shop_id])->firstOrFail();
        $is_live_open = ModulesShopModule::isModuleOpen($shop->id, ModulesShopModule::MODULE_SLUG_OBS_LIVE);
        if (!$is_live_open) {
            $this->error('not-open-obs');
        }
        $shop_obs = ShopObs::where(['shop_id' => $this->shop['id'], 'type'=> 'obs'])->first();
        $shop_obs->is_expire = $shop_obs->expire_time > time() ? 0:1;
        $shop_obs->expire_time = $shop_obs->expire_time ? hg_format_date($shop_obs->expire_time) : '';
        return $this->output($shop_obs);

    }

    /**
     * obs推流结束回调，获取回看地址
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obsCallback(Request $request){
        //拼接成流密钥
        $obs_flow_uuid = $request->stream_id;
        //存储回看请求数据
        $this->saveReplyData($request,$obs_flow_uuid);
        $alive = Alive::where(['obs_flow_uuid'=>$obs_flow_uuid])
            ->where('start_time','<',time())
            ->orderBy('start_time','desc')
            ->first();
        if($alive){
            $obs_flow = $alive->obs_flow ? unserialize($alive->obs_flow) : [];
            $obs_flow['obs_reply_url'] = $request->video_url;
            $obs_flow['obs_reply_duration'] = $request->duration;
            $obs_flow['obs_reply_file_size'] = $request->file_size;
            $alive->obs_flow = serialize($obs_flow);
            $alive->save();
        }

        return $this->output(['success'=>1]);

    }

    /**
     * 存储回看请求数据
     * @param $request
     */
    private function saveReplyData($request,$obs_flow_uuid){
        $param = [
            'obs_flow_uuid' => $obs_flow_uuid,
            'stream_id' => $request->stream_id,
            'reply_url' => $request->video_url,
            'duration'  => $request->duration,
            'file_size' => $request->file_size,
            'extra'     => json_encode($request->input())
        ];
        $obs_reply = new ObsReply();
        $obs_reply->setRawAttributes($param);
        $obs_reply->save();
    }


    /**
     * 开通在线直播
     */
    public function openOnlineLive(){

        $this->shop['id'] = request('shop_id') ? : $this->shop['id'];
        if(request('close')){
            //关闭在线直播
            Shop::where(['hashid' => $this->shop['id']])->update(['is_online_live'=>0]);
            $userShop = UserShop::where('shop_id', $this->shop['id'])->get();
            if ($userShop) {
                foreach ($userShop as $v) {
                    Cache::forever('change:'.$v->user_id,1);
                }
            }
            return $this->output(['success'=>1]);
        }
        $shop_id = $this->shop['id'];
        $shop = Shop::where(['hashid' => $shop_id])->firstOrFail();
        $is_live_open = ModulesShopModule::isModuleOpen($shop->id, ModulesShopModule::MODULE_SLUG_ONLINE_LIVE);
        if (!$is_live_open) {
            $this->error('cannot-open');
        }
        $m2o_config = config('define.M2O');
        $user_id = 'ds'.random_int(10000000,99999999);
        $member_info = $this->getM2oMember($m2o_config,$user_id, $this->shop['id']);
        $shop_obs = ShopObs::where(['shop_id' => $this->shop['id'],'type'=> 'online'])->first();
        if($shop_obs && $shop_obs->expire_time > time()){
            $shop_obs->expire_time = hg_format_date($shop_obs->expire_time);
            $shop_obs->member_name = $member_info->member_name;
            $shop_obs->password = $member_info->password;
            return $this->output($shop_obs);
        }
        $extra = json_decode($member_info['member_extra'],1);
        $shop_obs = $this->saveObsInfo($shop_obs,0,$member_info['member_id'],$extra['access_token'], $this->shop['id']);
        Shop::where(['hashid' => $this->shop['id']])->update(['is_online_live' => 1]);
        $userShop = UserShop::where('shop_id', $this->shop['id'])->get();
        if ($userShop) {
            foreach ($userShop as $v) {
                Cache::forever('change:'.$v->user_id,1);
            }
        }
        $shop_obs->member_name = $member_info->member_name;
        $shop_obs->password = $member_info->password;

        return $this->output($shop_obs);

    }


    /**
     * 注册m20会员
     * @param $m2o_config
     * @param $user_id
     * @param $shop_id
     * @return ShopM20Member|\Illuminate\Database\Eloquent\Model|null|static
     */
    private function getM2oMember($m2o_config,$user_id, $shop_id){

        if(ShopM20Member::where('member_name',$user_id)->first()){
            $user_id = 'ds'.random_int(10000000,99999999);
        }
        $shop_m2o_member = ShopM20Member::where(['shop_id' => $shop_id])->first();
        if($shop_m2o_member){
            return $shop_m2o_member;
        }
        $user_param = [
            'appid' => $m2o_config['member']['appid'],
            'appkey'=> $m2o_config['member']['appkey'],
            'member_name'   => $user_id,
            'password'      => random_int(100000,999999),
        ];
        $member_url = $m2o_config['api']['member_register'];
        $response = $this->curlClient(['query'=>$user_param],$member_url,'GET');
        if (isset($response['ErrorCode'])){
            $this->errorWithText($response['ErrorCode'],$response['ErrorText']);
        }
        if($response && is_array($response) && isset($response['0'])){
            $m2o_member = $response['0'];
            $save_member = [
                'shop_id'     => $shop_id,
                'member_id'   => isset($m2o_member['member_id']) ? $m2o_member['member_id'] : 0,
                'm2o_id'      =>  0,
                'member_guid' => isset($m2o_member['guid']) ? $m2o_member['guid'] : '',
                'member_name' => isset($m2o_member['member_name']) ? $m2o_member['member_name'] : '',
                'password'    => $user_param['password'],
                'member_extra'       => json_encode($m2o_member)
            ];
            $shop_m2o_member = new ShopM20Member();
            $shop_m2o_member->setRawAttributes($save_member);
            $shop_m2o_member->save();
        }
        return $shop_m2o_member;
    }

    /**
     * 获取推流地址列表
     */
    public function getPushUrlList(){

        $this->validateWithAttribute([
            'page'  => 'required|numeric',
            'count' => 'required|numeric',
            'shop_id'   => 'alpha_dash|size:18'
        ]);
        $count = request('count') ? : 20;

        $shop_obs = ShopObs::select(['*']);
        request('shop_id') && $shop_obs->where(['shop_id'=>request('shop_id')]);
        $lists = $shop_obs->orderByDesc('updated_at')->paginate($count);
        foreach ($lists as $list) {
            $list->expire_time = $list->expire_time ? hg_format_date($list->expire_time) : '';
        }
        return $this->output($this->listToPage($lists));
    }

}