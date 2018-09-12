<?php
/**
 * Created by PhpStorm.
 * User: huang an
 * Date: 17/3/30
 * Time: 15:34
 */
namespace App\Http\Controllers\Admin\Content;

use App\Events\AppEvent\AppContentEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Article;
use App\Models\Audio;
use App\Models\PromotionContent;
use App\Models\PromotionShop;
use App\Models\Videos;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\ContentType;
use App\Models\LimitPurchase;
use App\Models\MarketingActivity;
use App\Models\MemberCard;
use App\Models\Payment;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Vinkla\Hashids\Facades\Hashids;
use App\Jobs\PushContentRemind;

class ContentController extends BaseController{


    /**
     * @param string $sign
     * @return array
     * 内容新增/更新公用接口
     */
    public function formatBaseContent($sign=''){
        Cache::forget('h5:new:content:list:'.$this->shop['id']);   //新增或更新  最新内容时  清除缓存
        $data = [
            'title'       => request('title'),
            'shop_id'     => $this->shop['id'],
            'indexpic'    => hg_explore_image_link(request('indexpic')),
            'payment_type'=> intval(request('payment_type')),
            'up_time'     => strtotime(request('up_time')),
            'update_time' => time(),
            'update_user' => 0,
            'state'       => 0,
            'column_id'   => 0,
            'display'     => 1,
            'price'       => 0,
            'is_test'     => intval(request('is_test')) ? 1 : 0,    //是否试看
        ];
        $state = request('state');
        if($state == 2){
            $data['state'] = 2;
        } else{
            //第三方平台保存并上架按钮处理
            if(request('shelf')){
                $data['up_time'] = time();
                $data['state'] = 1;
            }else{
                if($data['up_time'] <= time()){
                    $data['state'] = 1;
                }
            }
        }
        !$sign && $data['create_time'] = time();
        !$sign && $data['create_user'] = $this->user['id'];
        $sign && $data['update_time'] = time();
        $sign && $data['update_user'] = $this->user['id'];
        if(request('payment_type')==1 || request('payment_type')==4 ){
            $this->validateWith(['column_id'=>'required']);
            $data['column_id'] = request('column_id');
            $data['price'] = request('price');
            $display = Column::where('id',request('column_id'))->value('display');
            $display == 0 && $data['display'] = 0;
        }
        if(request('payment_type')==2 || request('payment_type')==4){
            $this->validateWith(['price'=>'required']);
            $data['price'] = request('price');
        }
        if($data['price'] > MAX_ORDER_PRICE){
            $this->error('max-price-error');
        }
        // 加入到营销活动的商品，可以编辑基本信息，不能编辑价格和上架时间
        if($sign) {
            $c = Content::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id']])->firstOrFail();
            $is_join_market_activity = content_is_join_any_market_activity($this->shop['id'],$c->type,$c->hashid,MarketingActivity::COMMON_ACTIVITY);
            if($is_join_market_activity && request('price') && ($data['price'] != $c->price)) {
                $this->error('update-marketing-activity-content');
            }

            if($is_join_market_activity && ($data['state'] == 2)) {
                $this->error('update-marketing-activity-content');
            }

        }
        return $data;
    }

    /**
     * 处理商品推广
     */
    protected function contentPromotion($content_id, $data)
    {
        //专栏的内容不参与推广
        if ($data['column_id'] == 0) {
            $shop_id = $this->shop['id'];
            $promotion_setting = PromotionShop::where(['shop_id' => $shop_id])->first();
            //存在店铺推广配置
            if($promotion_setting){
                $params = [
                    'shop_id' => $shop_id,
                    'content_id' => $content_id,
                    'content_type' => $data['type'],
                    'promotion_rate_id' => $promotion_setting->promotion_rate_id,
                    'is_participate' => $promotion_setting->auto_join_promotion,
                ];
                PromotionContent::insert($params);
            }
        }
    }

    //数据新增公用接口
    public function createBaseContent($data=[])
    {
        $content_id = Content::insertGetId($data);
        $hashid = Hashids::encode($content_id);
        Content::where(['id'=>$content_id,'shop_id'=>$this->shop['id']])->update(['hashid' => $hashid]);
        $data['column_id'] != 0 && Column::where('id',$data['column_id'])->update(['update_time'=>time()]);
        $this->contentPromotion($hashid, $data);
        return $hashid;
    }

    //id验证
    public function checkId($type){
        $this->validateWithAttribute(['id'=>'required|alpha_dash'],['id'=>'内容id']);
        // 加入到营销活动的商品，可以编辑基本信息，不能编辑价格和上架时间
        // $pur_ids = hg_check_marketing($this->shop['id'],$type);
        // if(in_array(request('id'),$pur_ids)){
        //     return $this->error('marketing_activity');
        // }
    }

    //数据更新
    public function updateBaseContent($data=[])
    {
        Content::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id']])->update($data);
        $data['column_id'] != 0 && Column::where('id',$data['column_id'])->update(['update_time'=>time()]);
    }

    //获取返回数据
    public function getResponse($hashid)
    {
        $return= Content::where(['hashid'=>$hashid,'shop_id'=>$this->shop['id']])->first();
        $return->content_id = $return->hashid;
        $return->unserializerIndexpic();
        $type_id = ContentType::where('content_id',$hashid)->pluck('type_id')->toArray();
        $return->type_id = $type_id;
        $response['data'] = $return;
        $return['content'] = request('content');
        event(new AppContentEvent($return));
        return $response;
    }

    //筛选出参加限时购的各类型的id
//    protected function processLimitPurchaseData($type){
//        $ids = [];
//        $purchase_id = LimitPurchase::where(['shop_id'=>$this->shop['id']])->where('end_time','>',time())->pluck('hashid as purchase_id');
//        if($purchase_id){
//            foreach ($purchase_id as $item){
//                $ids = Redis::smembers('limitPurchase:'.$this->shop['id'].':'.$item.':'.$type);
//            }
//        }
//        return $ids?:[];
//    }

    //获取内容列表公用接口
    public function selectList($type=''){
        $sql = Content::where(['content.type'=>$type,'content.shop_id'=>$this->shop['id'],'column_id'=>0]);
        $price = request('price');
        $priceAtNoActivity = request('price_at_no_activity');

        if(isset($price)){
            $pur_ids = hg_check_marketing($this->shop['id'],$type);
            $pur_ids && $sql->whereNotIn('hashid',$pur_ids);
        }
        
        $type =='live' && $sql->join('live','live.content_id','=','content.hashid')->select('content.*','live.live_type','live_person','live_state','live.start_time','live.end_time','live.new_live');
        $type =='live' && request('live_type') && $sql->where('live_type',request('live_type'));
        request('title') && $sql->where('title','like','%'.request('title').'%');
        isset($price) && $sql->where('price','>',$price);
        isset($priceAtNoActivity) && $sql->where('price', '>', $priceAtNoActivity);
        if( $type == 'live') {
            request('start_time') && !request('end_time') && $sql->whereBetween('live.start_time',[strtotime(request('start_time')),time()]);
            request('end_time') && !request('start_time') && $sql->whereBetween('live.start_time',[0,strtotime(request('end_time'))]);
            request('start_time') && request('end_time') && $sql->whereBetween('live.start_time',[strtotime(request('start_time')),strtotime(request('end_time'))]);
        }else{
            request('start_time') && !request('end_time') && $sql->whereBetween('up_time',[strtotime(request('start_time')),time()]);
            request('end_time') && !request('start_time') && $sql->whereBetween('up_time',[0,strtotime(request('end_time'))]);
            request('start_time') && request('end_time') && $sql->whereBetween('up_time',[strtotime(request('start_time')),strtotime(request('end_time'))]);
        }
        request('column') && $sql->join('column','content.column_id','=','column.id')->where('column.title','like','%'.request('column').'%')->select('content.*');
        //筛选内容是否加入专栏
        if(request()->has('is_column'))
        {
            switch (request('is_column')) {
                case 0:
                    $sql->where('column_id',0);
                    break;
                case 1:
                    $sql->where('column_id','>',0);
                    break;
                default:
                    break;
            }
        }
        $state = request('state');
        if($state || isset($state)){
            if($type == 'live' && !request('is_state')){
                switch ($state){
                    case 0 :
                        $sql->where('start_time','>',time());
                        break;
                    case 1 :
                        $sql->where('start_time','<',time())
                            ->where('end_time','>',time());
                        break;
                    case 2 :
                        $sql->where('end_time','<',time());
                        break;
                    default:                        
                        break;
                }
            }else {
                switch ($state) {
                    case 0 :
                        $sql->where('up_time','>',time());
                        break;
                    case 1:
                        $sql->where('up_time', '<', time())
                            ->where(function ($query) {
                                $query->where('content.state', 1)->orWhere('content.state', 0);
                            });
                        break;
                    case 2 :
                        $sql->where('content.state', 2);
                        break;
                    default :
                        break;

                }
            }
        }
        $list = $sql
            ->orderBy('order_id')
            ->orderBy('up_time','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->paginate(request('count')?:10);
        return $list;
    }

    //列表处理公用接口
    public function formatList($list){
        if($list){
            foreach ($list as $k=>$v){
                // $v->market_activities = content_market_activities($this->shop['id'],$v->type, $v->hashid);
                if($v->type=='live'){
                    $v->live_person = json_decode($v->live_person, true);
                    $v->start_time > time() && $v->live_state = 0;
                    $v->start_time < time() && $v->end_time > time() && $v->live_state = 1;
                    $v->end_time < time() && $v->live_state = 2;
                    $v->start_time = date('Y-m-d H:i:s',$v->start_time);
                    $v->end_time = date('Y-m-d H:i:s',$v->end_time);
                }
                $v->column_id && $v->column_title = $v->column?$v->column->title:'';
                $v->state !=2 && $v->up_time < time() && $v->state = 1;
                $v->content_id = $v->hashid;
                $v->create_time = hg_format_date($v->create_time);
                $v->update_time = hg_format_date($v->update_time);
                $v->up_time = hg_format_date($v->up_time);
                $v->indexpic = hg_unserialize_image_link($v->indexpic);
                $v->column_id && $v->column_hashid = $v->column ? $v->column->hashid : '';
                $v->is_test = intval($v->is_test) ? 1 : 0;
            }

            $content_activity = [];
            foreach ($list as $i) {
                $content_activity[] = $i->type.'-'.$i->hashid;
            }
            $content_activity_map = contents_market_activity($this->shop['id'],$content_activity);
            foreach($list as $n) {
                $n->market_activities = $content_activity_map[$n->type.'-'.$n->hashid];
            }
        }
        return $this->listToPage($list);
    }

    //详情处理公用接口
    public function formatDetail($data){
        $return = [];
        if($data){
            $data->up_time = $data->up_time ? hg_format_date($data->up_time) : 0;
            $data->content_id = $data->hashid;
            $data->indexpic = hg_unserialize_image_link($data->indexpic);
            $type_id = ContentType::where('content_id',$data->content_id)->pluck('type_id')->toArray();
            $data->type_id = $type_id;
            $data->is_test = intval($data->is_test) ? 1 : 0;
            $data->column_id && $data->column_hashid = $data->column ? $data->column->hashid : '';
            $data->market_activities = content_market_activities($this->shop['id'],$data->type, $data->hashid);
            $return['data'] = $data;
        }
        return $return?:[];
    }


    /**
     * 内容新增数据验证公用接口
     */
    public function validateBaseContent(){
        $this->validateWithAttribute([
            'title'=>'required|max:128',
            'indexpic'=>'required',
            'payment_type'=>'required',
            'up_time'=>'date',
        ],[
            'title'=>'标题',
            'indexpic'=>'索引图',
            'payment_type'=>'收费类型',
            'up_time'=>'上架时间',
        ]);
    }

    /**
     * 上下架公用接口
     */
    public function shelf()
    {
        $this->validateWithAttribute(['id'=>'required','state'=>'required'],['id'=>'内容id','state'=>'上下架状态']);
        $state = request('state');
        $update = ['state'=>$state?1:2];
        $state==1 && $update['up_time'] = time();
        $ids = request('id');
        $params = explode(',',$ids);
        $sql = Content::where('shop_id',$this->shop['id'])->whereIn('hashid',$params)->whereIn('type',['article','audio','video','live']);
        $sqlA = clone $sql;

        $validContent = $sqlA->get();
        $validIdCount = $validContent->pluck('hashid')->count();

        if(count($params) !=  $validIdCount){
            return $this->errorWithText('contain-invalid-id','无效id');
        }
        $contentType = $validContent->pluck('type')->unique()->toArray();
        if (count($contentType) > 1 ) {
            return $this->errorWithText('multi-content-type-error', '不能同时下架/上架不同类型');
        }

        //营销活动限制下架
        if(isset($state) && $state==0){
            $this->check_is_any_join_common_activity($params, $contentType[0]);
        }
        $sqlB = clone $sql;
        $sqlB->update($update);
        Cache::forget('h5:new:content:list:'.$this->shop['id']);


        //专栏下的内容上架排到第一位获取数据
        if(request('type') == 'column') {
            //获取专栏id
            $column_id = Content::where('shop_id',$this->shop['id'])->whereIn('hashid',$params)->whereIn('type',['article','audio','video','live'])->value('column_id');
            $order_id = Content::where(['shop_id' => $this->shop['id'],'column_id'=>$column_id])
                ->where('column_id','>',0)
                ->whereIn('type',['article','audio','video','live'])
                ->orderBy('column_order_id')
                ->orderBy('top','desc')
                ->orderBy('update_time','desc')
                ->pluck('hashid');
        }else {
            $order_id = Content::where(['shop_id' => $this->shop['id'], 'type' => request('type') ?: 'live'])
                ->orderBy('order_id')
                ->orderBy('top', 'desc')
                ->orderBy('update_time', 'desc')
                ->orderBy('up_time', 'desc')
                ->pluck('hashid');
        }
        //为了不破坏事件里面的逻辑
        foreach($params as $value){
            $content = Content::where(['hashid'=>$value,'shop_id'=>$this->shop['id']])->whereIn('type',['article','audio','video','live'])->first();
            event(new AppContentEvent($content));
            $old_order = $content->order_id ?: (isset(array_flip($order_id->toArray())[$value]) ? array_flip($order_id->toArray())[$value] + 1 : 0);
            //上架时排序到第一位
            if (request('state')==1) {//专栏下的内容上架
                if (request('type') == 'column') {
                    hg_sort($order_id, $value, 0, $old_order, 'column_content');
                } else {
                    hg_sort($order_id, $value, 0, $old_order, $content->type);
                }
            }
        }
        return $this->output(['success'=>1]);
    }

    public function check_is_any_join_common_activity($ids, $type) {
        //营销活动限制
        $contents = [];
        foreach ($ids as $i) {
            $contents[] = $type.'-'.$i;
        }

        $f = is_any_contents_join_common_market_activity($this->shop['id'],$contents);
        if($f) {
            return $this->error('marketing_activity');
        }
    }

    /**
     * 置顶公用接口
     */
    public function contentTop(){
        $this->validateWithAttribute(['id'=>'required','top'=>'required'],['id'=>'内容id','top'=>'置顶状态']);
        $content = Content::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id')])->first();
        if($content){
            $content->top = request('top');
            $content->update_time = time();
            $content->update_user = $this->user['id'];
            $content->saveOrFail();
            return $this->output(['success'=>1]);
        }
        return $this->error('no_content');
    }

    /**
     * 根据类型查询所有
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContentByType()
    {
        $this->validateWith([
            'type'   => 'required|alpha_dash|in:article,audio,video,live,column,course,member_card',
            'type_id'   => 'numeric'
        ]);
        $data = [];
        switch (request('type')){
            case 'column':
                if(request('type_id')){
                    $content = ContentType::where(['type_id'=>request('type_id'),'type'=>'column'])->get();
                    if($content){
                        foreach ($content as $item) {
                            //筛选上架未锁定的专栏
                            if($item->belongToColumn
                                && $item->belongToColumn->getAttribute('state') == 1
                            ) {
                                $data[] = [
                                    'content_id' => $item->content_id,
                                    'title' => $item->belongToColumn ? $item->belongToColumn->getAttribute('title') : '',
                                    'indexpic' => $item->belongToColumn ? $item->belongToColumn->getAttribute('indexpic') : '',
                                    'price' => $item->belongToColumn ? $item->belongToColumn->getAttribute('price') : 0,
                                    'type' => 'column',
                                ];
                            }
                        }
                    }
                }else {
                    $data = Column::select('hashid as content_id', 'title', 'indexpic', 'price')
                        ->where(['shop_id' => $this->shop['id'], 'state' => 1])
                        ->get();
                    if ($data) {
                        foreach ($data as $item) {
                            $item->type = 'column';
                        }
                    }
                }
                break;
            case 'course':
                if(request('type_id')){
                    $content = ContentType::where(['type_id'=>request('type_id'),'type'=>'course'])->get();
                    if($content){
                        foreach ($content as $item) {
                            //筛选上架未锁定的课程
                            if($item->belongToCourse
                                && $item->belongToCourse->getAttribute('state') == 1
                                && $item->belongToCourse->getAttribute('is_lock') == 0
                            ) {
                                $data[] = [
                                    'content_id' => $item->content_id,
                                    'title' => $item->belongToCourse ? $item->belongToCourse->getAttribute('title') : '',
                                    'indexpic' => $item->belongToCourse ? $item->belongToCourse->getAttribute('indexpic') : '',
                                    'price' => $item->belongToCourse ? $item->belongToCourse->getAttribute('price') : 0,
                                    'type' => 'course',
                                ];
                            }
                        }
                    }
                }else {
                    $data = Course::select('hashid as content_id', 'title', 'indexpic', 'price')
                        ->where(['shop_id' => $this->shop['id'], 'is_lock' => 0])
                        ->get();
                    if ($data) {
                        foreach ($data as $item) {
                            $item->type = 'course';
                        }
                    }
                }
                break;
            case 'member_card':
                $data = MemberCard::select('hashid as content_id','title','price','options')
                    ->where(['shop_id'=>$this->shop['id'],'is_del'=>0, 'status'=>1])
                    ->orderBy('order_id')
                    ->orderBy('up_time','desc')
                    ->orderBy('updated_at','desc')
                    ->orderBy('created_at','desc')
                    ->get();
                if ($data) {
                    foreach ($data as $item) {
                        $item->type = 'member_card';
                        $item->options = $item->getOptions();
                    }
                }
                break;
            default:
                if(request('type_id')){
                    $content = ContentType::where(['type_id'=>request('type_id'),'type'=>request('type')])->get();
                    if($content){
                        foreach ($content as $item) {
                            //筛选上架未锁定的内容
                            if($item->belongToContent
                                && $item->belongToContent->getAttribute('up_time') < time()
                                && $item->belongToContent->getAttribute('state') != 2
                                && $item->belongToContent->getAttribute('is_lock') == 0
                            ) {
                                $data[] = [
                                    'content_id' => $item->content_id,
                                    'title' => $item->belongToContent ? $item->belongToContent->getAttribute('title') : '',
                                    'indexpic' => $item->belongToContent ? $item->belongToContent->getAttribute('indexpic') : '',
                                    'price' => $item->belongToContent ? $item->belongToContent->getAttribute('price') : 0,
                                    'type' => $item->type,
                                ];
                            }
                        }
                    }
                }else {
                    $data = Content::select('hashid as content_id', 'title', 'type', 'indexpic', 'price')
                        ->where(['shop_id' => $this->shop['id'], 'type' => request('type'), 'is_lock' => 0])
                        ->where('payment_type', '!=', 1)//筛选不属于专栏的
                        ->where('state', '!=', 2)//筛选下架的
                        ->where('up_time', '<', time())
                        ->orderBy('up_time','desc')
                        ->orderBy('create_time','desc')
                        ->orderBy('update_time','desc')
                        ->get();
                }
                break;
        }
        return $this->output($data);
    }

    /**
     * 创建或修改导航里的内容
     *
     * @param $data
     */
    protected function createOrUpdateType($data)
    {
        $new_data = [];
        ContentType::where('content_id',$data['content_id'])->delete();
        if (is_array($data['type_id']) && request('payment_type') != 1) {
            foreach ($data['type_id'] as $item) {
                $new_data[] = [
                    'content_id'  => $data['content_id'],
                    'type_id'     => $item,
                    'type'        => $data['type'],
                    'create_time' => time()
                ];
            }
            ContentType::insert($new_data);
        }
    }

    /**
     * 删除导航里的内容
     *
     * @param $content_id
     */
    protected function deleteType($content_id)
    {
        ContentType::whereIn('content_id',$content_id)->delete();
    }

    /**
     * @param $content_id
     * 如果内容删除，删除payment表中对应的数据
     */
    protected function deletePayment($content_id,$content_type)
    {
        Payment::where(['content_type'=>$content_type,'shop_id'=>$this->shop['id']])->whereIn('content_id',$content_id)->delete();
    }

    /**
     * 验证批量删除参数
    */
    protected function checkParam($type)
    {
        $this->validateWithAttribute(
            [
                'id' => 'required'
            ],
            [
                'id' => '内容参数'
            ]
        );
        $id = request('id');
        $ids = explode(',',$id);
        //营销活动限制
        if($ids){
            $contents = [];
            foreach ($ids as $i) {
                $contents[] = $type.'-'.$i;
            }

            $f = is_any_contents_join_common_market_activity($this->shop['id'],$contents);
            if($f) {
                return $this->error('marketing_activity');
            }
        }
        return $ids;
    }

    /**
     * 内容加入专栏，支持多条内容同时加入专栏
     * @return \Illuminate\Http\JsonResponse
     */
    public function contentToColumn(){
        $this->validateWithAttribute([
            'content_id'    => 'required|regex:/\w{12}(,\w(12))*$/',
            'column_id'     => 'required|alpha_dash'
        ],[
            'content_id'    => '内容id',
            'column_id'     => '专栏id'
        ]);
        $shopId = $this->shop['id'];
        $column = Column::select('id')->where(['shop_id'=>$shopId,'hashid'=>request('column_id')])->first();
        if(!$column){
            $this->error('column-no-find');
        }
        $ids = array_unique(explode(',',request('content_id')));
        //营销活动限制
        $pur_ids = hg_check_marketing($this->shop['id'],request('type'));
        if($ids){
            foreach ($ids as $id){
                if(in_array($id,$pur_ids)){
                    return $this->error('marketing_activity');
                }
            }
        }
        $contents = Content::select('hashid','title','indexpic','type','brief','up_time','state','update_user','create_user')->where('shop_id',$shopId)->whereIn('hashid',$ids)->get();
        if(!$contents->isEmpty()){
            foreach($contents as $content){
                $data = [
                    'shop_id' => $shopId,
                    'title' => $content->title,
                    'indexpic' => $content->indexpic,
                    'column_id' => $column->id,
                    'create_time' => time(),
                    'update_time' => time(),
                    'up_time' => $content->up_time,
                    'type' => $content->type,
                    'brief' => mb_substr(strip_tags($content->brief),0,60,'utf-8'),
                    'state' => 1,
                    'payment_type'  => 1,
                    'create_user'  => $content->create_user,
                    'update_user'  =>  $content->update_user,
                    'display'       => 1,
                ];
                $contentId = Content::insertGetId($data);
                $hashid = Hashids::encode($contentId);
                Content::where(['id'=>$contentId,'shop_id'=>$shopId])->update(['hashid'=>$hashid]);
                $this->pushContent($shopId,request('column_id'),$content->title,'column');
                if('article' == $content->type){
                    $articleContent = Article::where('content_id',$content->hashid)->value('content');
                    Article::insert(['content_id'=>$hashid,'content'=>$articleContent]);
                }elseif('audio' == $content->type){
                    $audioContent = Audio::select('content','url','file_name','test_url','test_file_name','size','test_size')->where('content_id',$content->hashid)->first();
                    Audio::insert([
                        'content_id' => $hashid,
                        'content' => $audioContent->content,
                        'url' => $audioContent->url,
                        'file_name' => $audioContent->file_name,
                        'test_url' => $audioContent->test_url,
                        'test_file_name' => $audioContent->test_file_name,
                        'size' => $audioContent->size,
                        'test_size' => $audioContent->test_size
                    ]);
                }elseif('video' == $content->type){
                    $videoContent = Video::select('patch','content','file_id','file_name','test_file_id','test_file_name','size','test_size')->where('content_id',$content->hashid)->first();
                    Video::insert([
                        'content_id' => $hashid,
                        'patch' => $videoContent->patch,
                        'content' => $videoContent->content,
                        'file_id' => $videoContent->file_id,
                        'file_name' => $videoContent->file_name,
                        'test_file_id' => $videoContent->test_file_id,
                        'test_file_name' => $videoContent->test_file_name,
                        'size' => $videoContent->size,
                        'test_size' => $videoContent->test_size
                    ]);
                }
            }
        }
//        $param = [
//            'column_id' => $column->id,
//            'display'   => intval($column->display) ? 1 : 0,
//            'payment_type'  => 1
//        ];
//        $content_ids = array_unique(explode(',',request('content_id')));
//        Content::where(['shop_id'=>$this->shop['id']])->whereIn('hashid',$content_ids)->whereIn('type',['article','audio','video','live'])->update($param);
//        if($content_ids){
//            foreach ($content_ids as $id){
//                Cache::forget('content:'.$this->shop['id'].':'.$id);
//            }
//        }
        return $this->output(['success'=>1]);
    }

    /**
     * 新建专栏专属内容
    */
    public function createColumnContent()
    {
        $this->validateWithAttribute([
            'column_id' => 'required',
            'type'    => 'required|in:article,audio,video',
            'title'     => 'required',
            'indexpic' => 'required',
            'content' => 'required',
            'url' => 'required_if:type,audio',
            'file_name' => 'required_if:type,audio,video',
            'file_id' => 'required_if:type,video',
            'is_up' => 'boolean',
            'patch' => 'required_if:type,video',
            'size' => 'required_if:type,video,audio',
            'brief' => 'required'
        ],[
            'column_id' => '栏目id',
            'type'    => '类型',
            'title'     => '标题',
            'indexpic' => '索引图',
            'content' => '内容',
            'url' => '上传地址',
            'file_name' => '文件名',
            'file_id' => '视频id',
            'is_up' => '是否立即上架',
            'patch' => '视频贴片',
            'size'=>'视频大小',
            'brief' => '简介'
        ]);

        $shopId = $this->shop['id'];
        $column = Column::select('id')->where(['shop_id'=>$shopId,'hashid'=>request('column_id')])->first();
        if(!$column){
            $this->error('column-no-find');
        }
        $data = [
            'shop_id'     => $shopId,
            'title'       => request('title'),
            'indexpic'    => hg_explore_image_link(request('indexpic')),
            'column_id'   => $column->id,
            'brief' => request('brief'),
            'display'     => 1,
            'up_time'       => request('is_up') ? time() : strtotime(request('up_time')),
            'state' => request('is_up') ? 1 : 0,
            'create_time' => time(),
            'type' => request('type'),
            'payment_type'  => 1,
            'create_user'  => $this->user['id'],
            'update_user'  =>  $this->user['id'],
        ];
        $contentId = Content::insertGetId($data);
        $hashid = Hashids::encode($contentId);
        Content::where(['id'=>$contentId,'shop_id'=>$shopId])->update(['hashid'=>$hashid]);
        switch(request('type')){
            case 'article':
                Article::insert([
                    'content_id' => $hashid,
                    'content' => request('content')
                ]);
                break;
            case 'audio':
                Audio::insert([
                    'content_id' => $hashid,
                    'url' => request('url'),
                    'file_name' => request('file_name'),
                    'size' => request('size'),
                    'content' => request('content'),
                ]);
                break;
            case 'video':
                Video::insert([
                    'content_id' => $hashid,
                    'patch' => hg_explore_image_link(request('patch')),
                    'file_id' => request('file_id'),
                    'file_name' => request('file_name'),
                    'size' => request('size'),
                    'content' => request('content'),
                ]);
                break;
        }
        if(request('is_up')){
            $this->pushContent($this->shop['id'],request('column_id'),request('title'),'column');
        }else{
            $times = strtotime(request('up_time'))-time();
            $job = (new PushContentRemind($this->shop['id'],request('column_id'),request('title'),'column'))->onQueue(DEFAULT_QUEUE)
                ->delay(Carbon::now()->addSeconds($times));
            dispatch($job);
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 更新专栏专属内容
    */
    public function updateColumnContent($id)
    {
        $this->validateWithAttribute([
            'type'    => 'required|in:article,audio,video',
            'title'     => 'required',
            'indexpic' => 'required',
            'content' => 'required',
            'url' => 'required_if:type,audio',
            'file_name' => 'required_if:type,audio,video',
            'file_id' => 'required_if:type,video',
            'is_up' => 'boolean',
            'patch' => 'required_if:type,video',
            'size' => 'required_if:type,video,audio',
            'brief' => 'required'
        ],[
            'type'    => '类型',
            'title'     => '标题',
            'indexpic' => '索引图',
            'content' => '内容',
            'url' => '上传地址',
            'file_name' => '文件名',
            'file_id' => '视频id',
            'is_up' => '是否立即上架',
            'patch' => '视频贴片',
            'size' => '视频大小',
            'brief' => '简介'
        ]);
        $shopId = $this->shop['id'];
        $obj = Content::where(['shop_id'=>$shopId,'hashid'=>$id])->first();
        if(!$obj){
            $this->error('data-not-fond');
        }
        $obj->title = request('title');
        $obj->brief = request('brief');
        $obj->indexpic = hg_explore_image_link(request('indexpic'));
        $obj->up_time = request('is_up') ? time() : strtotime(request('up_time'));
        $obj->state = request('is_up') ? 1 : 0;
        $obj->update_time = time();
        $obj->save();
        switch(request('type')){
            case 'article':
                Article::where('content_id',$id)->update(['content'=>request('content')]);
                break;
            case 'audio':
                Audio::where('content_id',$id)->update(['url'=>request('url'),'file_name'=>request('file_name'),'size' => request('size'),'content'=>request('content')]);
                break;
            case 'video':
                Video::where('content_id',$id)->update([
                    'patch'=>hg_explore_image_link(request('patch')),
                    'file_id'=>request('file_id'),
                    'file_name'=>request('file_name'),
                    'size' => request('size'),
                    'content'=>request('content'),
                ]);
                break;
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 专栏下内容批量删除
    */
    public function deleteColumnContent()
    {
        $this->validateWithAttribute([
            'content_id'    => 'required',
            'column_id'       => 'required',
        ],[
            'content_id'    => '内容id',
            'column_id'       => '栏目id'
        ]);
        $shopId = $this->shop['id'];
        $obj  = Content::whereIn('hashid',explode(',',request('content_id')))->where(['shop_id'=>$shopId,'column_id'=>request('column_id')])->get();
        if(!$obj->isEmpty()){
            foreach($obj as $value){
                switch($value->type){
                    case 'article':
                        Article::where('content_id',$value->hashid)->delete();
                        break;
                    case 'audio':
                        Audio::where('content_id',$value->hashid)->delete();
                        break;
                    case 'video':
                        Video::where('content_id',$value->hashid)->delete();
                        break;
                }
                $value->delete();
            }
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 专栏下内容批量上下架
    */
    public function shelfColumnContent()
    {
        $this->validateWithAttribute([
            'content_id'    => 'required',
            'column_id'       => 'required',
            'status' => 'required|in:1,2'
        ],[
            'content_id'    => '内容id',
            'column_id'       => '栏目id',
            'status' => '状态'
        ]);
        $shopId = $this->shop['id'];
        Content::whereIn('hashid',explode(',',request('content_id')))
            ->where(['shop_id'=>$shopId,'column_id'=>request('column_id')])
            ->update(['state'=>request('status')]);
        return $this->output(['success'=>1]);
    }

    /**
     * 内容设为试看(支持批量)
     * @return \Illuminate\Http\JsonResponse
     */
    public function contentToTest(){
        $this->validateWithAttribute([
            'content_id'    => 'required|regex:/\w{12}(,\w(12))*$/',
            'is_test'       => 'required|numeric|in:0,1',
        ],[
            'content_id'    => '内容id',
            'is_test'       => '是否试看'
        ]);
        $content_id = explode(',',request('content_id'));
        $is_test = intval(request('is_test')) ? 1 : 0;
        Content::where(['shop_id' => $this->shop['id']])
            ->whereIn('type',['article','audio','video','live'])
            ->whereIn('hashid',$content_id)
            ->update(['is_test'=>$is_test]);
        return $this->output(['success'=>1]);
    }

    /**
     * 我的内容列表排序
     * @return \Illuminate\Http\JsonResponse
     */
    public function sort(){
        $this->validateWithAttribute([
            'id'    => 'required|alpha_dash|size:12',
            'order' => 'required|numeric',
            'type'  => 'required|alpha_dash|in:article,audio,video,live'
        ], [
            'id'    => '内容id',
            'order' => '排序位置',
            'type'  => '内容分类'
        ]);


        $order_id = Content::where(['shop_id' => $this->shop['id'],'type'=>request('type')])
            ->orderBy('order_id')
            ->orderBy('up_time','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->pluck('hashid');

        $old_order = Content::where(['hashid'=>request('id'),'type'=>request('type')])->firstOrFail() ? Content::where(['hashid'=>request('id'),'type'=>request('type')])->firstOrFail()->order_id : (isset(array_flip($order_id->toArray())[request('id')]) ? array_flip($order_id->toArray())[request('id')] +1 : 0);
        hg_sort($order_id,request('id'),request('order'),$old_order,request('type'));
        return $this->output(['success'=>1]);

    }


}