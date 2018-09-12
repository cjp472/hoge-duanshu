<?php
/**
 * 直播内容
 */
namespace App\Http\Controllers\Admin\Content;


use App\Events\AppEvent\AppContentDeleteEvent;
use App\Events\ErrorHandle;
use App\Events\CurlLogsEvent;
use App\Models\Alive;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\ModulesShopModule;
use App\Models\Shop;
use App\Models\ShopObs;
use App\Models\PromotionContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AliveController extends ContentController
{
    /**
     * 列表
     */
    public function lists()
    {
        $list = $this->selectList('live');
        $response = $this->formatList($list);
        return $this->output($response);
    }

    /**
     * 编辑详情接口
     */
    public function detail($id)
    {
        $detail = $this->selectDetail($id);
        $response = $this->formatLiveDetail($detail);
        return $this->output($response);
    }

    /**
     * 新增
     */
    public function create()
    {
        $this->validateLive();
        $param = $this->formatBaseContent();
        $param['type'] = 'live';
        $hashid = $this->createBaseContent($param);
        $live = $this->formatLive($hashid);
        $this->createLive($live);
        $data = ['content_id'=>$hashid,'type_id'=>request('type_id'),'type'=>'live'];
        $this->createOrUpdateType($data);
        $this->createLiveGroup($live);
        return $this->output($this->getResponse($hashid));
    }

    /**
     * 更新
     */
    public function update()
    {
        $this->checkId('live');
        $this->validateLive();
        $param = $this->formatBaseContent(1);
        $this->updateBaseContent($param);
        $live = $this->formatLive(request('id'));
        Cache::forget('content:'.$this->shop['id'].':'.$live['content_id']);
        $this->updateLive($live);
        $data = ['content_id'=>request('id'),'type_id'=>request('type_id'),'type'=>'live'];
        $this->createOrUpdateType($data);
        $this->createLiveGroup($live);
        $response = $this->getResponse(request('id'));
        return $this->output($response);
    }

    /**
     * 删除
     */
    public function delete()
    {
        $params = $this->checkParam('live');
        Content::where('shop_id',$this->shop['id'])->whereIn('hashid',$params)->delete();
        Alive::whereIn('content_id',$params)->delete();
        PromotionContent::where('content_type','live')->whereIn('content_id',$params)->delete();
        $this->deleteType($params);
        $this->deletePayment($params,'live');//删除该内容对应的订阅数据
        //为了不破坏事件里面的逻辑
        foreach($params as $value){
            $data = ['content_id'=>$value,'shop_id'=>$this->shop['id'],'type'  => 'live'];
            event(new AppContentDeleteEvent($data));
            Redis::del('alive:message:'.$this->shop['id'].':'.$value);
            Redis::del('alive:message:lecturer:'.$this->shop['id'].':'.$value);
            $this->deleteLiveGroup($value);
        }
        
        return $this->output(['success'=>1]);
    }

    private function formatLiveDetail($data){
        $return = [];
        if($data){
            $data->start_time > time() && $data->live_state = 0;
            $data->start_time < time() && $data->end_time > time() && $data->live_state = 1;
            $data->end_time < time() && $data->live_state = 2;
            $data->start_time = date('Y-m-d H:i:s',$data->start_time);
            $data->end_time = date('Y-m-d H:i:s',$data->end_time);
            $data->up_time = date('Y-m-d H:i:s',$data->up_time);
            $live_person = json_decode($data->live_person, true);
            $param = [];
            if($live_person && is_array($live_person)){
                foreach ($live_person as $item){
                    $item['admire'] = in_array($item['id'],Redis::smembers('admire:'.$this->shop['id'].':'.$data->hashid))?1:0;
                    $param[] = $item;
                }
            }
            $data->is_test = intval($data->is_test) ? 1 : 0;
            $data->live_person = $param;
            $data->content_id = $data->hashid;
            $data->type_id = $data->content_type->pluck('type_id');
            $data->indexpic = hg_unserialize_image_link($data->indexpic);
            $data->live_indexpic = hg_unserialize_image_link($data->live_indexpic);
            $data->obs_flow = $data->obs_flow ? unserialize($data->obs_flow) : [];
            unset($data->content_type);
            $type_id = ContentType::where('content_id',$data->content_id)->pluck('type_id')->toArray();
            $data->type_id = $type_id;
            $data->market_activities = content_market_activities($this->shop['id'],$data->type, $data->hashid);
            $return['data'] = $data;
        }
        return $return?:[];
    }

    private function selectDetail($id){
        $data = Content::join('live','live.content_id','=','content.hashid')
            ->where(['content.hashid'=>$id,'content.shop_id'=>$this->shop['id']])
            ->select('content.type','content.id','content.hashid','content.title','content.indexpic','content.payment_type','content.column_id',
                'content.price','content.state','live.brief','live.live_indexpic','live.live_type','live_flow','live.file_id','live.file_name','live.start_time',
                'live.end_time','content.up_time','live.live_person','content.is_test','live.obs_flow','live.live_describe','live.new_live',
                'content.view_count','content.unique_member','content.sales_total', 'content.comment_count')
            ->firstOrFail();
        return $data;
    }

    private function validateLive(){
        //obs类型不需要验证结束时间，特殊处理
        $dont_check_time_type = [4,5];
        if(in_array(request('live_type'),$dont_check_time_type)){
            request()->merge(['end_time'=> hg_format_date(strtotime(request('start_time').'+1 day'))]);
        }
        $this->validateWithAttribute([
            'brief'=>'required',
            'live_indexpic'=>'required',
            'start_time'=>'required|before:end_time',
            'end_time'=>'required|after:start_time',
            'live_type'=>'required|numeric|in:1,2,3,4,5',
            'live_describe'=>'required',
            'live_person' =>'required',
            'title'=>'required|max:128',
            'indexpic'=>'required',
            'payment_type'=>'required',
            'up_time'=>'before:start_time',
        ],[
            'brief'=>'直播描述',
            'live_indexpic'=>'直播封面',
            'start_time' => '开始时间',
            'end_time' => '结束时间',
            'live_type' => '直播类型',
            'live_describe' => '直播描述',
            'live_person' => '直播人员',
            'title' => '标题',
            'indexpic' => '索引图',
            'payment_type' => '收费类型',
            'up_time' => '上架时间',
        ]);
    }

    private function formatLive($hashid){
        $data =  [
            'content_id' => $hashid,
            'brief' => request('brief'),
            'live_indexpic' => hg_explore_image_link(request('live_indexpic')),
            'start_time' => strtotime(request('start_time')),
            'end_time' => strtotime(request('end_time')),
            'live_type' => request('live_type'),
            'live_state' => 0,
            'live_describe' => request('live_describe'),
        ];
        if(request('live_type')==2){
            $this->validateWith(['file_id'=>'required','file_name'=>'required']);
            $data['file_id'] = request('file_id');
            $data['file_name'] = request('file_name');
        }
        if(request('live_type')==3) { //直播类型选择直播流
            $data['live_flow'] = request('live_flow');
        }
        $live = Alive::where('content_id',$hashid)->first();


        if((!$live || ($live && !$live->obs_flow))) {

            //obs直播流
            if (request('live_type') == 4) {
                $shop = Shop::where(['hashid' => $this->shop['id']])->first();
                if (!$shop) {
                    return response([
                        'error' => 'shop-not-found',
                        'message' => trans('validation.shop-does-not-exist'),
                    ]);
                }
                $is_obs_live_open = ModulesShopModule::isModuleOpen($shop->id, ModulesShopModule::MODULE_SLUG_OBS_LIVE);
                if (!$is_obs_live_open) {
                    $this->error('not-open-obs');
                }

                $shop_obs = ShopObs::where(['shop_id' => $this->shop['id'], 'type' => 'obs'])->first();

                $data['obs_flow'] = serialize([
                    'obs_url' => request('obs_url') ? : $shop_obs->play_url,
                    'obs_secret' => ''
                ]);
                $obs_secret = request('obs_secret') ?: ShopObs::where(['shop_id' => $this->shop['id'], 'type' => 'obs'])->value('stream_id');
                if (!$obs_secret) {
                    $this->error('not_obs_secret');
                }
                $data['obs_flow_uuid'] = $obs_secret;//存储直播id和obs直播流地址关联，方便推流地址回看处理

            }
            //在线直播
            if (request('live_type') == 5) {
                $shop = Shop::where(['hashid' => $this->shop['id']])->first();
                if (!$shop) {
                    return response([
                        'error' => 'shop-not-found',
                        'message' => trans('validation.shop-does-not-exist'),
                    ]);
                }
                $is_online_live_open = ModulesShopModule::isModuleOpen($shop->id, ModulesShopModule::MODULE_SLUG_ONLINE_LIVE);
                if (!$is_online_live_open) {
                    $this->error('not-open-online-live');
                }
                $shop_obs = ShopObs::where(['shop_id' => $this->shop['id'], 'type' => 'online'])->first();
                $data['obs_flow'] = serialize([
                    'obs_url' => $shop_obs->play_url,
                    'obs_secret' => ''
                ]);
                $data['obs_flow_uuid'] = $shop_obs->stream_id;//存储直播id和obs直播流地址关联，方便推流地址回看处理
            }
        }
        if($live && $live->live_state == 2 && in_array(request('live_type'),[4,5])){
            unset($data['start_time']);
            unset($data['end_time']);
            unset($data['obs_flow']);
            unset($data['obs_flow_uuid']);
        }
        $person = request('live_person');
        $ids = array_column($person,'id');
        if(array_diff_assoc($ids,array_unique($ids))){
            $this->error('repeat_person');
        }else{
            $data['live_person'] = json_encode($person);
        }

        if(in_array(request('live_type'),[2,3,4,5,'2','3','4','5'])){
            $data['new_live'] = 1;
        } //直播类型 1 语音直播 2 视频录播+语音直播 3 自定义直播  4 OBS录屏直播 5 手机互动直播

        return $data;
    }

    private function createLive($live){
        $this->formatLiveAdmire($live);
        $this->createLiveType($live['content_id']);
        Alive::insert($live);
        $brief = mb_substr(strip_tags(request('live_describe')),0,100);  //截取描述入库
        Content::where('hashid',$live['content_id'])->update(['brief'=>$brief]);
    }


    private function createLiveGroup($live){
        $url = config('define.python_duanshu.api.ceate_live_chat_group');
        $url = sprintf($url,$live['content_id']);
        $appId = config('define.inner_config.sign.key');
        $appSecret = config('define.inner_config.sign.secret');
        $client = hg_verify_signature([],'',$appId,$appSecret, $this->shop['id']);
        try{

            $response = $client->put($url,['http_errors'=>true]);
            $return = $response->getBody()->getContents();
            $data = json_decode($return);
            if(array_key_exists('error',$data) && $data['error'] != 0){
                throw new Exception('创建直播聊天组失败 '.$data['message'].' live id'.$live['content_id']);
            }
            event(new CurlLogsEvent($return, $client, $url));
        }catch (\Exception $exception){
            event(new ErrorHandle($exception));
            return $this->error('create_chat_group_fail');
        }
    }

    private function deleteLiveGroup($liveId)
    {
        $url = config('define.python_duanshu.api.delete_member_to_chat_group');
        $url = sprintf($url, $liveId);
        $appId = config('define.inner_config.sign.key');
        $appSecret = config('define.inner_config.sign.secret');
        $client = hg_verify_signature([], '', $appId, $appSecret, $this->shop['id']);
        try {

            $response = $client->delete($url);
            $return = $response->getBody()->getContents();
            // $data = json_decode($payload);
            event(new CurlLogsEvent($return, $client, $url));
        } catch (\Exception $exception) {
            event(new ErrorHandle($exception));
        }
    }


    private function createLiveType($id){
        $type = ContentType::where('content_id',$id)->get();
        if($type){
            ContentType::where('content_id',$id)->delete();
        }
        $type_id = request('type_id');
        if($type_id && is_array($type_id)){
            foreach($type_id as $v){
                $data[] = [
                    'content_id'=>$id,
                    'type_id' => $v,
                    'type' =>'live',
                ];
            }
            ContentType::insert($data);
        }
    }

    private function formatLiveAdmire($live){
        $live_person = request('live_person');
        if($live_person && is_array($live_person)){
            foreach ($live_person as $item){
                if(isset($item['admire']) && intval($item['admire'])==1){
                    Redis::sadd('admire:'.$this->shop['id'].':'.$live['content_id'],$item['id']);
                }else{
                    Redis::srem('admire:'.$this->shop['id'].':'.$live['content_id'],$item['id']);
                }
            }
        }
    }

    private function updateLive($live){
        $this->formatLiveAdmire($live);
        $this->createLiveType($live['content_id']);
        Alive::where(['content_id'=>request('id')])->update($live);
        $brief = mb_substr(strip_tags(request('live_describe')),0,100);  //截取描述入库
        Content::where('hashid',$live['content_id'])->update(['brief'=>$brief]);
    }

    /**
     * 结束直播
     */
    public function endLive(){

        $this->validateWithAttribute([
            'id'    => 'required|alpha_dash|size:12',
        ],[
            'id'    => '直播id'
        ]);

        $live = Alive::where(['content_id'=>request('id')])->firstOrFail();
        //直播未开始
        if($live->start_time > time()){
            $this->error('live-not-start');
        }
        //直播已结束
        if($live->end_time < time()){
            $this->error('live-already-end');
        }
        //进行中的直播结束直播
        $live->end_time = time();
        $live->live_state = 2;
        $live->save();
        return $this->output(['success'=>1]);
    }



}