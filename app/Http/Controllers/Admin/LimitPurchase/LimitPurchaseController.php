<?php
/**
 * Created by PhpStorm.
 * User: huang an
 * Date: 18/1/31
 * Time: 下午2:09
 */

namespace App\Http\Controllers\Admin\LimitPurchase;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\MarketingActivity;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Vinkla\Hashids\Facades\Hashids;
use App\Models\LimitPurchase;

class LimitPurchaseController extends BaseController
{

    /**
     * @return \Illuminate\Http\JsonResponse
     * 限时购活动列表
     */
    public function lists(){
        $sql = LimitPurchase::where('shop_id',$this->shop['id']);
        $status = request('status');
        if(isset($status)){
            switch ($status){
                case 1:
                    $sql->where(['switch'=>1])->where('start_time','<',time())->where('end_time','>', time());
                    break;
                case 0:
                    $sql->where(['switch'=>1])->where('start_time','>',time());
                break;
                case 2:
                    $sql->where(['switch'=>1])->where('end_time','<',time());
                break;
                case -1:
                    $sql->where(['switch'=>0]);
                break;
            }
        }
        request('title') && $sql = $sql->where('title', 'like', '%'.request('title').'%');
        $lists = $sql->orderBy('order_id')->orderByDesc('top')->orderByDesc('created_at')->paginate(request('count')?:10);
        $limit_purchase = $this->listToPage($lists);
        if($limit_purchase && $limit_purchase['data']){
            foreach ($limit_purchase['data'] as $item){
                if($item->switch == 1){
                    if($item->start_time < time() && $item->end_time > time()){
                        $item->status = 1;
                    }elseif($item->start_time > time()){
                        $item->status = 0;
                    }elseif($item->end_time < time()){
                        $item->status = 2;
                    }
                }else{
                    $item->status = -1;
                }
                $item->indexpic = hg_unserialize_image_link($item->indexpic);
                $item->start_time = hg_format_date($item->start_time);
                $item->end_time = hg_format_date($item->end_time);
            }
        }
        return $this->output($limit_purchase);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 限时购活动新增
     */
    public function create(){
        $data = $this->processData();
        $limit_purchase = new LimitPurchase();
        $limit_purchase->setRawAttributes($data);
        $limit_purchase->save();
        $hashid = Hashids::encode($limit_purchase->id);
        $limit_purchase->hashid = $hashid;
        $limit_purchase->save();
        $this->processContentToRedis($limit_purchase);
        return $this->output($limit_purchase);
    }

    private function processContentToRedis($limit){
        $expire_time = ($limit->end_time - time()) > 0 ? $limit->end_time - time() : 0;
        if(intval($limit->range)==2){
            foreach (unserialize($limit->contents)[0] as $type => $value) {
                if($value){
                    foreach ($value as $item) {
                        $key = 'purchase:'.$this->shop['id'].':'.$type.':'.$item;
                        Redis::set($key,$limit->hashid);
                        Redis::expire($key,$expire_time);
                        $market = new MarketingActivity();
                        $market->shop_id = $this->shop['id'];
                        $market->content_id = $item;
                        $market->content_type = $type;
                        $market->marketing_type = 'limit_purchase';
                        $market->start_time = $limit->start_time;
                        $market->end_time = $limit->end_time;
                        $market->save();
//                        $keys = 'limitPurchase:'.$this->shop['id'].':'.$limit->hashid.':'.$type;
//                        Redis::sadd($keys,$item);
//                        Redis::expire($keys,$expire_time);
                    }
                }
            }
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 限时购活动更新
     */
    public function update(){
        $this->validateId();
        $limit_purchase = $this->getLimitPurchase(request('id'));
        $data = $this->processUpdateData();
        $limit_purchase->setRawAttributes($data);
        $limit_purchase->save();
        $limit_purchase->hashid = request('id');
        return $this->output($limit_purchase);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 更新活动结束时间
     */
    public function updateTime(){
        $this->validateWithAttribute(['id'=>'required','end_time'=>'required'],['id'=>'限时购活动id','end_time'=>'结束时间']);
        $limit_purchase = $this->getLimitPurchase(request('id'));
        if($limit_purchase){
            $limit_purchase->end_time = strtotime(request('end_time'));
            $limit_purchase->save();
            $this->processContentToRedis($limit_purchase);
        }
        return $this->output(['success'=>1]);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * 限时活动详情
     */
    public function detail($id){
        $limit_purchase = $this->getLimitPurchase($id);
        $limit_purchase->range = intval($limit_purchase->range);
        $limit_purchase->indexpic = $limit_purchase->indexpic?unserialize($limit_purchase->indexpic):[];
        $limit_purchase->contents = $this->getLimitPurchaseContents($limit_purchase->contents);
        if($limit_purchase->switch == 1){
            if($limit_purchase->start_time < time() && $limit_purchase->end_time > time()){
                $limit_purchase->status = 1;
            }elseif($limit_purchase->start_time > time()){
                $limit_purchase->status = 0;
            }elseif($limit_purchase->end_time < time()){
                $limit_purchase->status = 2;
            }
        }else{
            $limit_purchase->status = -1;
        }
        $limit_purchase->start_time = hg_format_date($limit_purchase->start_time);
        $limit_purchase->end_time = hg_format_date($limit_purchase->end_time);
        return $this->output($limit_purchase);
    }

    /**
     * @param $id
     * 限时购活动开关
     */
    public function changer($id){
        $this->validateWithAttribute(['switch'=>'required'],['switch'=>'活动开关']);
        $limit_purchase = $this->getLimitPurchase($id);
        if($limit_purchase && request('switch')==0){
            $this->clearLimitPurchaseContent($limit_purchase);
        }
        $limit_purchase->switch = request('switch');
        $limit_purchase->save();
        return $this->output(['success'=>1]);
    }

    private function clearLimitPurchaseContent($limit){
        foreach (unserialize($limit->contents)[0] as $type => $value) {
            if($value){
                foreach ($value as $item) {
                    $key = 'purchase:'.$this->shop['id'].':'.$type.':'.$item;
                    Redis::del($key);
                    $keys = 'limitPurchase:'.$this->shop['id'].':'.$limit->hashid.':'.$type;//删除旧数据用，针对新数据无效
                    Redis::srem($keys,$item);//删除旧数据用，针对新数据无效
                    MarketingActivity::where(['shop_id'=>$this->shop['id'],'content_id'=>$item,'content_type'=>$type])->delete();
                }
            }
        }
    }


    /**top
     * @param $id
     * 限时购置顶
     */
    public function top(){
        $this->validateWithAttribute(['top'=>'required'],['top'=>'置顶状态']);
        $limit_purchase = $this->getLimitPurchase(request('id'));
        $limit_purchase->top = request('top');
        $limit_purchase->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 限时购排序
     */
    public function sort(){
        $this->validateWithAttribute([
            'id'    => 'required|alpha_dash|size:12',
            'order' => 'required|numeric',
        ], [
            'id'    => '限时购id',
            'order' => '排序位置',
        ]);

        $order_id = LimitPurchase::where(['shop_id' => $this->shop['id']])
            ->orderBy('order_id')
            ->orderBy('top','desc')
            ->pluck('hashid');

        $old_order = LimitPurchase::where(['hashid'=>request('id')])->firstOrFail() ? LimitPurchase::where(['hashid'=>request('id')])->firstOrFail()->order_id : (isset(array_flip($order_id->toArray())[request('id')]) ? array_flip($order_id->toArray())[request('id')] +1 : 0);
        hg_sort($order_id,request('id'),request('order'),$old_order,'limit_purchase');
        return $this->output(['success'=>1]);
    }

    /**
     * @param $id
     * 限时购活动删除
     */
    public function delete($id){
        $limit_purchase = $this->getLimitPurchase($id);
        if($limit_purchase){
            $this->deletePurchaseRedis($id);
            $this->clearLimitPurchaseContent($limit_purchase);
            $limit_purchase->delete();
        }else{
            $this->error('no_data');
        }
        return $this->output(['success'=>1]);
    }
    
    private function deletePurchaseRedis($id){
        $type = ['article','audio','video','live'];
        foreach ($type as $item){
            Redis::del('limitPurchase:'.$this->shop['id'].':'.$id.':'.$item);//删除旧数据用，针对新数据无效
        }
    }

    /**
     * @return array|mixed
     * 统计
     */
    public function analysis(){
        $result = Cache::get('purchase:seven:analysis:'.$this->shop['id']);
        if($result) {
            return json_decode($result);
        }
        $type = 'm-d'; $begin = date("m-d", mktime(0, 0, 0, date("m"),1, date("Y")));
        $info = $this->getOrderData(0,time());
        $back = $keys = $values = $list = [];
        if($info){
            foreach ($info as $key=>$item) {
                $hour = date($type, $item['order_time']);
                isset($back[$hour]) ? $back[$hour] += $item['price'] : $back[$hour]=$item['price'];
            }
            for($i = $begin;$i <= date($type);$i++)
            {
                $date = str_pad($i,2,0,STR_PAD_LEFT);
                $keys[] = $date;
                $values[] = isset($back[$date]) ? sprintf('%.2f',$back[$date]) : 0;
            }
            $list = ['keys'=>$keys,'values'=>$values];
            Cache::put('purchase:seven:analysis:'.$this->shop['id'],json_encode($list),EXPIRE_DAY/60);
        }
        return $this->output($list?:[]);
    }

    public function getOrderData($start,$end){
        $order_id = Redis::smembers('limit:purchase:'.$this->shop['id'].':'.request('id'))?:[];
        $data = Payment::whereIn('order_id',$order_id)
            ->where(['shop_id'=>$this->shop['id']])
            ->whereBetween('order_time',[$start,$end])
            ->get();
        return $data ?: [];
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 限时购订购列表
     */
    public function recordLists(){
        $order_id = Redis::smembers('limit:purchase:'.$this->shop['id'].':'.request('id'));
        $order_id  && $sql = Payment::where(['shop_id'=>$this->shop['id']]);
        request('nickname') && $sql->where('nickname','like','%'.request('nickname').'%');
        request('source') && $sql->where('source',request('source'));
        request('title') && $sql->where('content_title','like','%'.request('title').'%');
        $data = $sql->whereIn('order_id',$order_id)->paginate(request('count')?:10);
        if($data){
            foreach ($data as $item){
                $item->order_time = $item->order_time?hg_format_date($item->order_time):0;
            }
        }
        return $this->output($this->listToPage($data));
    }

    private function getLimitPurchaseContents($contents){
        if($contents == 1){
            return $contents;
        }elseif($contents) {
            $content = [];
            foreach (unserialize($contents)[0] as $key => $item) {
                $data = Content::where(['shop_id'=>$this->shop['id'],'type'=>$key])->whereIn('hashid',$item)->select('type','indexpic','title','hashid','price')->get();
                foreach ($data as $value){
                    $value->content_id = $value->hashid;
                }
                $content = array_merge($content,$data->toArray());
            }
            return $content;
        }
    }


    private function validateId(){
        $this->validateWithAttribute(['id'=>'required'],['id'=>'限时购活动id']);
    }

    private function getLimitPurchase($id){
        return LimitPurchase::where(['hashid'=>$id,'shop_id'=>$this->shop['id']])->first();
    }


    private function processData(){
        $this->validateWithAttribute([
            'title'     => 'required|max:20',
            'describe'  => 'max:300',
            'start_time'=> 'required',
            'end_time'  => 'required',
            'discount'  => 'required',
            'range'     => 'required',
            'condition' => 'required',
        ],[
            'title'     => '活动标题',
            'describe'  => '活动描述',
            'start_time'=> '开始时间',
            'end_time'  => '结束时间',
            'discount'  => '折扣',
            'range'     => '范围',
            'condition' => '使用条件',
        ]);
        $indexpic = request('indexpic');
        $describe = request('describe');
        $data = [
            'shop_id'   => $this->shop['id'],
            'title'     => request('title'),
            'describe'  => isset($describe)?$describe:'',
            'indexpic'  => isset($indexpic)?serialize($indexpic):'',
            'start_time'=> strtotime(request('start_time')),
            'end_time'  => strtotime(request('end_time')),
            'discount'  => request('discount'),
            'range'     => request('range'),
            'condition' => request('condition'),
            'shelf'     => request('shelf') ? : 0,
        ];
        request('shelf') == 1 && $data['up_time'] = time();
        if(request('range') == 1){
            $data['contents'] = 1;
        }else{
            $data['contents'] = serialize(request('contents'));
        }
        return $data;
    }

    private function processUpdateData(){
        $this->validateWithAttribute([
            'title'     => 'sometimes|required|string|max:20',
            'describe'  => 'sometimes|required|string|max:300',
            'indexpic' => 'sometimes|required|array',
            'end_time' => 'sometimes|date_format:Y-m-d H:i:s'
        ],[
            'title'     => '活动标题',
            'describe'  => '活动描述',
            'indexpic' => '索引图',
            'end_time' => '结束时间'
        ]);
        $data = [];
        request('title') && $data['title'] = request('title');
        request('describe') && $data['describe'] = request('describe');
        request('indexpic') && $data['indexpic'] = serialize(request('indexpic'));
        request('end_time') && $data['end_time'] = strtotime(request('end_time'));
        return $data;
    }







}











