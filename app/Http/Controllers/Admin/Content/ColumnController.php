<?php
/**
 * 专栏内容
 */
namespace App\Http\Controllers\Admin\Content;

use App\Events\AppEvent\AppColumnEvent;
use App\Events\Content\CreateEvent;
use App\Events\Content\EditEvent;
use App\Events\AppEvent\AppContentDeleteEvent;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Column;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\LimitPurchase;
use App\Models\MarketingActivity;
use App\Models\PromotionContent;
use App\Models\PromotionShop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Vinkla\Hashids\Facades\Hashids;

class ColumnController extends BaseController
{
    /**
     * @return mixed
     * 专栏列表
     */
    public function lists()
    {
        $list = $this->selectList();
        $response = $this->formatList($list);
        return $this->output($response);
    }

    /**
     * @return mixed
     * 专栏下内容列表
     */
    public function contents(){
        $this->validateWithAttribute(['column_id'=>'required'],['column_id'=>'专栏id']);
        $lists = $this->getContentLists();
        $response = $this->formatContent($lists);
        return $this->output($this->listToPage($response));
    }

    /**
     * @param $id
     * @return mixed
     * 专栏详情
     */
    public function detail($id)
    {
        $response = $this->selectDetail($id);
        return $this->output($response);
    }

    /**
     * @return mixed
     * 专栏新增
     */
    public function create()
    {
        $this->ValidateColumn();
        $param = $this->formatColumn();
        $response = $this->createColumn($param);
        return $this->output($response);
    }

    /**
     * 删除
     *
     * @return void
     */
    public function delete(){
        $shopId = $this->shop['id'];

        $this->validateWithAttribute(['id'=>'required|array|max:50|min:1','id.*'=>'alpha_dash|max:64'], ['id'=>'专栏id']);

        $inputIdCollection = new Collection(request('id'));

        $validColumn = Column::where(['shop_id'=>$shopId])->whereIn('hashid',request('id'))->select('hashid','title','id')->get()->unique('hashid');
        $validColumnMap = $validColumn->groupBy('id');
        $validColumnId = $validColumn->pluck('id')->toArray();
        $validColumnHashId = $validColumn->pluck('hashid')->toArray();
        
        $invalidId = $inputIdCollection->diff($validColumnHashId);
        if($invalidId->count()){
            return $this->errorWithText('invalid-column-id', '无效id '.join($invalidId->toArray(),'、'));
        }

        $columnContentCount = Content::where(['shop_id'=>$shopId])->where('type','!=','column')->whereIn('column_id',$validColumnId)
            ->groupBy('column_id')->select('column_id',DB::raw('count(*) as count'))->get();
        $notEmptyColumn = $columnContentCount->filter(function($value,$key){
            return $value->count > 0;
        });

        $count = $notEmptyColumn->count();
        if($count){
            $titles = [];
            foreach ($notEmptyColumn as $value) {
                    $first = $validColumnMap[$value->column_id]->first();
                    $titles[]= $first->title;
            }
            $errMsg = '当前专栏'.join($titles,'、').'目录下有正在上架的内容，请删除后再操作';
            return $this->errorWithText('not-empty-column',$errMsg);
        }

        Content::whereIn('hashid',$validColumnHashId)->where('shop_id',$shopId)->where('type','column')->delete();
        Content::whereIn('column_id', $validColumnId)->where('shop_id',$shopId)->delete();
        Column::whereIn('hashid',$validColumnHashId)->where('shop_id',$shopId)->delete();
        PromotionContent::where('content_type', 'column')->whereIn('content_id', $validColumnHashId)->delete();

        foreach($validColumnHashId as $i){
            $data = ['content_id'=>$i,'shop_id'=>$shopId,'type'=>'column'];
            event(new AppContentDeleteEvent($data));
        }
        return $this->output(['success'=>1]);
    }

    /**
     * @return mixed
     * 专栏更新
     */
    public function update()
    {
        $this->validateWithAttribute(['id'=>'required|alpha_dash'],['id'=>'专栏id']);
        $param = $this->formatColumn(1);
        $response = $this->updateColumn($param);
        Cache::forget('column:'.$this->shop['id'].':'.request('id'));
        Cache::forget('content:'.$this->shop['id'].':'.'column'.':'.request('id'));
        return $this->output($response);
    }

    /**
     * @return mixed
     * 专栏完结状态更改
     */
    public function finish(){
        $this->validateWithAttribute(['id'=>'required','finish'=>'required'],['id'=>'专栏id','finish'=>'完结状态']);
        $params = explode(',',request('id'));
        foreach ($params as $value) {
            event(new EditEvent($value,'column',[
                'up_time'   => time(),
            ],$this->shop['id'],$this->user));
        }
        Column::where('shop_id',$this->shop['id'])->whereIn('hashid',$params)->update(['finish'=>request('finish')]);
        $this->getTypeId($params);
        return $this->output(['success'=>1]);
    }

    /**
     * 拼接redis键值
     */
    private function getTypeId($id){
        $type_ids = ContentType::whereIn('content_id',$id)->pluck('type_id');
        if($type_ids){
            foreach ($type_ids as $item){
                $types[] = 'h5:column:list:'.$item.':'.$this->shop['id'];
            }
        }
        $types[] = 'h5:column:list:'.$this->shop['id'];
        Redis::del($types);
    }

    /**
     * @return mixed
     * 专栏上下架
     */
    public function shelf()
    {
        $this->validateWithAttribute(['id'=>'required','state'=>'required'],['id'=>'专栏id','state'=>'上下架状态']);
        $this->formatShelf();

        $params = explode(',',request('id'));
        $this->getTypeId($params);
        $order_id = Column::where(['shop_id' => $this->shop['id']])
            ->orderBy('order_id')
            ->orderBy('top','desc')
            ->orderBy('update_time','desc')->pluck('hashid');
        //为了不破坏事件里面的逻辑
        foreach($params as $value){
            $column = Column::where(['hashid'=>$value,'shop_id'=>$this->shop['id']])->first();
            event(new AppColumnEvent($column));
            event(new EditEvent($value,'column',[
                'state' => request('state') ? 1 : 2,//内容上下架状态值和专栏不同
                'up_time'   => time()
            ],$this->shop['id'],$this->user));

            if(request('state') == 1) {
                $old_order = $column ? $column->order_id : (isset(array_flip($order_id->toArray())[$value]) ? array_flip($order_id->toArray())[$value] + 1 : 0);
                hg_sort($order_id, $value, 0, $old_order, 'column');
            }
        }
        return $this->output(['success'=>1]);
    }

    /**
     * @return mixed
     * 显示/隐藏
     */
    public function display(){
        $this->validateWithAttribute(['id'=>'required|alpha_dash','display'=>'required'],['id'=>'专栏id','display'=>'显示/隐藏']);
        Column::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id']])->update(['display'=>request('display')]);
        event(new EditEvent(request('id'),'column',['display' => request('display')],$this->shop['id'],$this->user));
        $this->getTypeId([request('id')]);
        return $this->output(['success'=>1]);
    }

    /**
     * 专栏置顶
     */
    public function top(){
        $this->validateWithAttribute(['id'=>'required','top'=>'required'],['id'=>'专栏id','top'=>'置顶状态']);
        $column = Column::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id']])->first();
        $this->getTypeId([request('id')]);
        if($column){
            $column->top = request('top');
            $column->update_time = time();
            $column->update_user = $this->user['id'];
            $column->saveOrFail();
            return $this->output(['success'=>1]);
        }
        return $this->error('no_column');
    }

    private function formatShelf(){
        $update = ['state'=>request('state')];
        $params = explode(',',request('id'));
        Column::where('shop_id',$this->shop['id'])->whereIn('hashid',$params)->update($update);
    }

    private function selectList(){
        $sql = Column::where(['shop_id'=>$this->shop['id']]);
        $state = request('state');$finish = request('finish');
        $priceAtNoActivity = request('price_at_no_activity');

        if($state || isset($state)){
            $sql->where('state',intval(request('state')));
        }
        if($finish || isset($finish)){
            $sql->where('finish',intval(request('finish')));
        };
        $price = request('price');
        if(isset($price)){
            $sql->where('price','>',$price);
            $pur_ids = hg_check_marketing($this->shop['id'],'column');
            $pur_ids && $sql->whereNotIn('hashid',$pur_ids);
        }

        request('title') && $sql->where('title','like','%'.request('title').'%');
        request('start_time') && !request('end_time') && $sql->whereBetween('create_time',[strtotime(request('start_time')),time()]);
        request('end_time') && !request('start_time') && $sql->whereBetween('create_time',[0,strtotime(request('end_time'))]);
        request('start_time') && request('end_time') && $sql->whereBetween('create_time',[strtotime(request('start_time')),strtotime(request('end_time'))]);
        isset($priceAtNoActivity) && $sql->where('price', '>', $priceAtNoActivity);

        $list = $sql->orderBy('order_id')
            ->orderBy('top','desc')
            ->orderBy('update_time','desc')
            ->paginate(request('count')?:10);
        return $list;
    }



    private function getContentLists(){
        $id = Column::where(['hashid'=>request('column_id'),'shop_id'=>$this->shop['id']])->value('id');
        $sql = Content::where(['column_id'=>$id,'shop_id'=>$this->shop['id']])->whereNotIn('type',['column','course']);
        request('title') && $sql = $sql->where('title','like','%'.request('title').'%');
        $state = request('state');
        if($state && $state==1){
            $sql->where('up_time','<', time())->where(function ($query) {
                $query->where('state',1)->orWhere('state',0);
            });
        }elseif($state && $state==2){
            $sql->where('state',2);
        }
        $lists = $sql->orderBy('column_order_id','asc')
            ->orderBy('up_time','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->paginate(request('count')?:10);
        return $lists;
    }

    private function formatContent($data){
        if($data){
            foreach ($data as $k=>$v){
                $v->content_id = $v->hashid;
                $v->price = $v->price?$v->price:0.00;
                $v->state !=2 && $v->up_time < time() && $v->state = 1;
                $v->create_time = $v->create_time?date('Y-m-d H:i:s',$v->create_time) : 0;
                $v->update_time = $v->update_time?date('Y-m-d H:i:s',$v->update_time) : 0;
                $v->up_time = $v->up_time?date('Y-m-d H:i:s',$v->up_time) : 0;
                $v->indexpic = hg_unserialize_image_link($v->indexpic);
                $v->is_test = intval($v->is_test) ? 1 : 0;
            }
        }
        return $data?:[];
    }

    private function formatList($data){
        if($data){
            foreach ($data as $k=>$v){
                $v->column_id = $v->hashid;
                $v->create_time = date('Y-m-d H:i:s',$v->create_time);
                $v->update_time = date('Y-m-d H:i:s',$v->update_time);
                $v->stage = count($v->content);
                $v->indexpic = hg_unserialize_image_link($v->indexpic);
                $v->charge = intval($v->charge);
                $v->state = intval($v->state);
                $v->type = 'column';
                unset($v->content);
                unset($v->mainpic);
            }
        }
        return $this->listToPage($data);
    }

    private function selectDetail($id){
        $detail = Column::where(['hashid'=>$id,'shop_id'=>$this->shop['id']])->firstOrFail();
        if($detail){
            $detail->create_time = $detail->create_time?date('Y-m-d H:i:s',$detail->create_time):0;
            $detail->update_time = $detail->update_time?date('Y-m-d H:i:s',$detail->update_time):0;
            $detail->column_id = $detail->hashid;
            $detail->type_id = $detail->column_type->pluck('type_id');
            $detail->charge = intval($detail->charge);
            $detail->finish = intval($detail->finish);
            $detail->stage = count($detail->content);
            $detail->indexpic = hg_unserialize_image_link($detail->indexpic);
            $detail->mainpic = hg_unserialize_image_link($detail->mainpic);
            $detail->market_activities = content_market_activities($this->shop['id'],'column', $detail->hashid);
            unset($detail->content);
            unset($detail->column_type);
            $return['data'] = $detail;
        }
        return $return?:[];
    }

    private function createColumn($column){
        $data = new Column();
        $data->setRawAttributes($column);
        $data->create();
        $hashid = $data->hashid;
        $this->createColumnType($hashid);   //保存专栏分类
        event(new AppColumnEvent($data));
        event(new CreateEvent(
            $hashid,array_merge($this->setContentColumn($data),['column_id'=>$data->id]),'column',$this->shop['id'],$this->user));
        $this->createPromotionContent($hashid, 'column');
        $data->charge = intval($data->charge);
        $response['data'] = $data;
        return $response;
    }
    private function createColumnType($id){
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
                    'type' =>'column',
                    'create_time'   => time()
                ];
                $types[] = 'h5:column:list:'.$v.':'.$this->shop['id'];
            }
            $types[] = 'h5:column:list:'.$this->shop['id'];
            Redis::del($types);  //清除redis下这个类型的数据
            ContentType::insert($data);
        }
    }

    private function updateColumn($data){
        Column::where('hashid',request('id'))->update($data);
        $this->createColumnType(request('id'));   //更新专栏分类
        $return = Column::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id']])->first();
        event(new AppColumnEvent($return));
        event(new EditEvent($return->hashid,'column',$this->setContentColumn($return),$this->shop['id'],$this->user));
        $return->column_id = $return->hashid;
        $return->charge = intval($return->charge);
        $response['data'] = $return;
        return $response;
    }

    private function formatColumn($sign=''){
        $column = [
            'title' => request('title'),
            'shop_id' => $this->shop['id'],
            'indexpic' => hg_explore_image_link(request('indexpic')),
            'mainpic' => hg_explore_image_link(request('mainpic')),
            'brief' => request('brief'),
            'describe' => request('describe'),
            'update_user'=> 0 ,
            'update_time'=> time(),
            'state' => 1,
            'display' => 1,
        ];
        switch(request('charge')){
            case 1:
                $this->validateWithAttribute(['price'=>'required'],['price'=>'专栏价格']);
                $column['charge'] = 1; $column['price'] = request('price');break;
            case 0:
                $column['charge'] = 0; $column['price'] = 0; break;
        }
        if($column['price'] > MAX_ORDER_PRICE){
            $this->error('max-price-error');
        }
        !$sign && $column['create_time'] = time();
        !$sign && $column['create_user'] = $this->user['id'];
        !$sign && $column['top'] = 1;
        $sign && $column['update_user'] = $this->user['id'];
        $sign && $column['update_time'] = time();
        $sign && $column['state'] = request('state');
        if(request('shelf')){
            $column['state'] = 1;
        }

        // 加入到营销活动的商品，可以编辑基本信息，不能编辑价格
        if($sign) {
            $c = Column::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id']])->firstOrFail();
            $is_join_market_activity = content_is_join_any_market_activity($this->shop['id'],'column',$c->hashid,MarketingActivity::COMMON_ACTIVITY);
            if($is_join_market_activity && request('price') && ($column['price'] != $c->price)) {
                $this->error('update-marketing-activity-content');
            }
            if(is_null(request('price'))){
                unset($data['price']);
            }
        }
        return $column;
    }

    private function ValidateColumn(){
        $this->validateWithAttribute([
            'title'=>'required|max:150',
            'indexpic'=>'required',
            'brief' => 'required|max:250',
            'describe' => 'required',
            'charge'   => 'required',
            'mainpic' => ''
        ],[
           'title'=>'专栏标题',
           'indexpic'=>'专栏封面',
           'brief'=>'专栏简介',
           'describe'=>'专栏描述',
           'charge' =>'是否收费',
           'mainpic' =>'详情页顶图'
        ]);
    }

    /**
     * 专栏下内容设置成单卖或免费
     */
    public function changePayment(){
        $this->validateWith(['content_id'=>'required']);
        $content = Content::where('hashid',request('content_id'))->firstOrFail();
        $update = ['column_id'=>0,'update_time'=>time(),'update_user'=>$this->user['id'],'shop_id'=>$this->shop['id']];
        if($content->payment_type==1 || $content->payment_type==4){
            if($content->price==0.00 || $content->price==0){
                $update['payment_type'] = 3;
            }else{
                $update['payment_type'] = 2;
            }
            Content::where('hashid',request('content_id'))->update($update);
            return $this->output(['success'=>1]);
        }
        return $this->error('not_belong_column');
    }

    private function setContentColumn($info)
    {
        return [
            'title'  => $info->title,
            'brief' => strip_tags($info->brief),
            'indexpic'  => $info->indexpic,
            'state' => $info->state,
            'payment_type'  => $info->charge ? 2 : 3 ,
            'price'  => $info->price,
        ];
    }

    /**
     * 专栏排序
     * @return \Illuminate\Http\JsonResponse
     */
    public function sort(){
        $this->validateWithAttribute([
            'id' => 'required|alpha_dash',
            'order' => 'required|numeric'
        ], [
            'id' => '专栏id',
            'order' => '排序位置'
        ]);


        $order_id = Column::where(['shop_id' => $this->shop['id']])
            ->orderBy('order_id')
            ->orderBy('top','desc')
            ->orderBy('update_time','desc')->pluck('hashid');
        $old_order = Column::where(['hashid'=>request('id')])->firstOrFail() ? Column::where(['hashid'=>request('id')])->firstOrFail()->order_id : (isset(array_flip($order_id->toArray())[request('id')]) ? array_flip($order_id->toArray())[request('id')] +1 : 0);
        hg_sort($order_id,request('id'),request('order'),$old_order,'column');

        return $this->output(['success'=>1]);
    }

    /**
     * 专栏下内容排序
     * @return \Illuminate\Http\JsonResponse
     */
    public function contentSort(){
        $this->validateWithAttribute([
            'id'    => 'required|alpha_dash',
            'order' => 'required|numeric',
            'column_id' => 'required|alpha_dash'
        ], [
            'id'    => '内容id',
            'order' => '排序位置',
            'column_id' => '专栏id'
        ]);

        $column = Column::where(['hashid'=>request('column_id'),'shop_id'=>$this->shop['id']])->firstOrFail();

        $order_id = Content::where(['shop_id' => $this->shop['id'],'column_id'=>$column->id])
            ->where('column_id','>',0)
            ->whereIn('type',['article','audio','video','live'])
            ->orderBy('column_order_id')
            ->orderBy('top','desc')
            ->orderBy('update_time','desc')
            ->pluck('hashid');
        $old_order = Content::where(['hashid'=>request('id'),'column_id'=>$column->id])->whereIn('type',['article','audio','video','live'])->firstOrFail() ? Content::where(['hashid'=>request('id'),'column_id'=>$column->id])->whereIn('type',['article','audio','video','live'])->first()->column_order_id : (isset(array_flip($order_id->toArray())[request('id')]) ? array_flip($order_id->toArray())[request('id')] +1 : 0);
        hg_sort($order_id, request('id'), request('order'), $old_order, 'column_content');
        return $this->output(['success'=>1]);
    }
}