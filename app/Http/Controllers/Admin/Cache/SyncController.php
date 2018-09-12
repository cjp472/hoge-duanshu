<?php
/**
 * Created by PhpStorm.
 * User: huangan
 * Date: 2017/6/6
 * Time: 上午10:24
 */

namespace  App\Http\Controllers\Admin\Cache;

use App\Http\Controllers\Admin\BaseController;
use App\Jobs\SyncPromotionRecord;
use App\Models\Alive;
use App\Models\AliveMessage;
use App\Models\Column;
use App\Models\Comment;
use App\Models\Content;
use App\Models\Payment;
use App\Models\Member;
use App\Models\Praise;
use App\Models\PromotionRecord;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SyncController extends BaseController{

    //直播问题状态数据同步
    public function problemStatus(){
        $problem = AliveMessage::where(['problem'=>1,'problem_state'=>0])->select('shop_id','content_id','id')->get();
        if($problem){
            foreach ($problem as $item){
                Redis::sadd('problem:status:'.$item->shop_id.':'.$item->content_id,$item->id);
            }
        }
        return $this->output(['success'=>1]);
    }

    //直播管理模式状态同步
    public function livePattern(){
        $live = Alive::select('content_id','manage','gag')->get();
        if($live){
            foreach ($live as $item){
                Cache::forever('live:pattern:manage:'.$item->content_id,$item->manage);
                Cache::forever('live:pattern:gag:'.$item->content_id,$item->gag);
            }
        }
        return $this->output(['success'=>1]);
    }

    //点赞状态同步
    public function praiseStatus(){
        $result = Praise::select('*')->get();
        foreach ($result as $item){
            Cache::forever('comment:praise:status:'.$item->comment_id.':'.$item->member_id,$item->praise_num);
        }
        return $this->output(['success'=>1]);
    }

    //点赞总数同步
    public function praiseSum(){
        $result = Comment::select('praise','id')->get();
        foreach ($result as $item){
            Cache::forever('comment:praise:sum:'.$item->id,$item->praise);
        }
        return $this->output(['success'=>1]);
    }

    //音视频播放量/完播量/分享量同步
    public function playCount(){
        $content = Content::where(['shop_id'=>$this->shop['id'],'hashid'=>request('content_id')])->first();
        Cache::forever('playCount:'.$this->shop['id'].':'.request('content_id'),$content->play_ount);
        Cache::forever('endPlayCount:'.$this->shop['id'].':'.request('content_id'),$content->end_play_count);
        Cache::forever('shareCount:'.$this->shop['id'].':'.request('content_id'),$content->share_count);
        return $this->output(['success'=>1]);
    }

    /**
     * 清除banner和导航分类缓存
     */
    public function clearShopCache()
    {
        $shop_ids = Shop::skip(request('page') ?: 1)->take(request('count') ?: 1000)->pluck('hashid');
        if ($shop_ids) {
            foreach ($shop_ids as $shop_id) {
                Cache::forget('banner:' . $shop_id);
                Cache::forget('navigation:' . $shop_id);
                Cache::forget('share:' . $shop_id);
            }
        }
        return $this->output(['success'=>1]);
    }
    //支付内容数据同步
    public function paymentSync(){
//        $content = Payment::select('order_id', 'shop_id', 'content_id', 'user_id', 'content_type')->get();
//        if ($content) {
//            foreach ($content as $item) {
//                if ($item->content_type == 'column') {
//                    Cache::forever('payment:' . $item->shop_id . ':' . $item->user_id . ':' . $item->content_id . ':' . $item->content_type, $item->order_id);
//                } else {
//                    Cache::forever('payment:' . $item->shop_id . ':' . $item->user_id . ':' . $item->content_id, $item->order_id);
//                }
//            }
//        }
        return $this->output(['success'=>1]);
    }


    public function wechatMember(){
        $member = Member::where(['source'=>'wechat'])->get();
        foreach($member as $item){
            $item->setKeyType('string');
            $item->id = $item->uid;
            Cache::forever('wechat:member:'.$item->id,json_encode($item));
        }
        return $this->output(['success'=>1]);
    }
    //订阅专栏老数据同步
    public function subscribeSync(){
        $content = Payment::where('payment.content_type','column')->leftJoin('column','column.hashid','=','payment.content_id')->leftJoin('content','content.column_id','=','column.id')->select('content.shop_id','payment.user_id','content.hashid')->get()->toArray();
        if($content){
            foreach ($content as $item) {
                if($item['shop_id'] && $item['user_id'] && $item['hashid']){
                    Redis::sadd('subscribe:h5:'.$item['shop_id'].':'.$item['user_id'],$item['hashid']);
                }
            }
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 直播消息同步到redis
     */
    public function liveMessage(){
        $message = AliveMessage::orderBy('time','asc')->get();
        if($message){
            foreach ($message as $item) {
                $live = $this->getAliveData($item->content_id,$item->member_id);
                $live && $live->lecturer==1 && $param[$item['shop_id'].':'.$item['content_id'].':lecturer'][] = ($item);
                $param[$item['shop_id'].':'.$item['content_id']][] = ($item);
            }
            if($param){
                foreach ($param as $key=>$value){
                    foreach ($value as $k=>$v) {
                        $v->kid = $k;
                        if(preg_match('/lecturer/',$key)){
                            Redis::rpush('alive:message:lecturer:'.$v->shop_id.':'.$v->content_id,serialize($v));
                        }else{
                            Redis::rpush('alive:message:'.$v->shop_id.':'.$v->content_id,serialize($v));
                        }
                    }
                }
            }
        }
        return $this->output(['success'=>1]);
    }

    private function getAliveData($content_id,$member_id){
        $alive = Alive::where(['content_id'=>$content_id])->first();
        $alive && $person_id = array_pluck(json_decode($alive->live_person, true),'id');
        $alive && $alive->lecturer = in_array($member_id,$person_id) ? 1 : 0;
        return $alive;
    }

    /**
     * 推广记录数据分条
     */
    public function syncPromotionRecord(){

        $admin_id = config('define.admin_signature_id');
        if(!in_array($this->user['id'],$admin_id)){
            $this->errorWithText('no-permission',trans('validation.no-permission',['attributes' => '数据同步']));
        }
        $promotion_record = PromotionRecord::where(['promotion_type'=>'promotion'])->where('visit_id','!=','')->get();
        if($promotion_record->isNotEmpty()){
            foreach ($promotion_record as $item) {
                $job = new SyncPromotionRecord($item);
                dispatch($job->onQueue(DEFAULT_QUEUE));
            }
        }
        return $this->output(['success'=>1]);
    }
}