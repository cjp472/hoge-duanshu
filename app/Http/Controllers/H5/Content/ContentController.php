<?php
/**
 * 内容h5端
 */
namespace App\Http\Controllers\H5\Content;

use App\Events\ContentViewEvent;
use App\Events\ErrorHandle;
use App\Events\PayEvent;
use App\Events\SubscribeEvent;
use App\Events\SubscribeFreeColumnEvent;
use App\Jobs\CheckPaymentExpire;
use App\Jobs\ContentPlayCount;
use App\Jobs\SubscribeForget;
use App\Models\Alive;
use App\Models\AliveMessage;
use App\Models\CardRecord;
use App\Models\ClassContent;
use App\Models\Column;
use App\Models\Comment;
use App\Models\Content;
use App\Http\Controllers\H5\BaseController;
use App\Models\Course;
use App\Models\ContentType;
use App\Models\LimitPurchase;
use App\Models\MarketingActivity;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopContentRemind;
use App\Models\Type;
use App\Models\Videos;
use App\Models\Views;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use qcloudcos\Cosapi;
use App\Models\PromotionContent;

class ContentController extends BaseController
{

    /**
     * @return mixed
     * H5端最新内容列表c
     */
    public function lists(){
        $content = $this->selectContent();
        $response = $this->formatContent($content);
        $data = $this->listToPage($response);
        return $this->output($data);
    }

    /**
     * @return mixed
     * H5端专栏列表
     */
    public function columnLists(){
        $column = $this->selectColumn();
        $response = $this->formatColumn($column);
        $data = $this->listToPage($response);
        return $this->output($data);
    }

    /**
     * @return mixed
     * H5端直播列表
     */
    public function aliveLists(){
        $live = $this->selectAlive();
        $response = $this->formatContent($live);
        $data = $this->listToPage($response);
        return $this->output($data);
    }

    /**
     * @return mixed
     * 专栏下内容列表
     */
    public function contents(){
        $this->validateWithAttribute([
            'column_id'=>'required',
            'order'     => 'alpha_dash|in:desc,asc',
        ],[
            'column_id' => '专栏id',
            'order'     => '内容顺序'
        ]);
        $list = $this->getColumnContent();
        $response = $this->formatContent($list);
        return $this->output($this->listToPage($response));
    }

    /**
     * @return mixed
     * H5端专栏详情
     */
    public function columnDetail($id){
        $this->shopInstance = Shop::where(['hashid'=>$this->shop['id']])->firstOrFail();
        $detail = $this->selectColumnDetail($id);
        $detail->type = 'column';
        $detail->column_id = $detail->id;
        event(new ContentViewEvent($detail,$this->member));
        $response = $this->formatColumnDetail($detail);
        return $this->output($response);
    }

    /**
     * @param $id
     * @return mixed
     * H5端内容详情
     */
    public function detail($id){
        $this->shopInstance = Shop::where(['hashid'=>$this->shop['id']])->firstOrFail();
        $detail = $this->selectDetail($id);
        $response = $this->formatDetail($detail);
        event(new ContentViewEvent($detail,$this->member));
        return $this->output($response);
    }

    /**
     * @param $id
     * @return mixed
     * 付费前专栏详情
     */
    public function freeColumnDetail($id){
        $this->shopInstance = Shop::where(['hashid'=>$this->shop['id']])->firstOrFail();
        $detail = $this->selectFreeColumnDetail($id);
        $detail->type = 'column';
        $detail->column_id = $detail->id;
        event(new ContentViewEvent($detail,$this->member));
        $response = $this->formatColumnDetail($detail);
        return $this->output($response);
    }

    /**
     * @param $id
     * @return mixed
     * 付费前内容详情
     */
    public function freeDetail($id){
        $this->shopInstance = Shop::where(['hashid'=>$this->shop['id']])->firstOrFail();
        $detail = $this->selectFreeDetail($id);
        $response = $this->formatDetail($detail);
        event(new ContentViewEvent($detail,$this->member));
        return $this->output($response);
    }

    /**
     * 用户订阅的专栏列表
     */
    public function subscribeColumnList(){
        $data = $this->getSubscribeColumnList();
        $response = $this->getSubscribeColumnListResponse($data);
        return $this->output($response);
    }

    public function subscribeFreeColumn(){
        $this->validateWithAttribute(['column_id'=>'required'],['column_id'=>'专栏id']);

        $column = Column::where(['shop_id'=>$this->shop['id'],'hashid'=>request('column_id')])->firstOrFail();
        $member = Member::where(['shop_id'=>$this->shop['id'],'uid'=>$this->member['id']])->first();

        $subed = $this->checkColumnSubscribe($column->hashid);
        if ($subed) {
            return $this->error('already-subscribed');
        }

        $subPermDetail = $this->checkFreeSubscribePermission($member, $column->price, $column->join_membercard);
        
        if (!$subPermDetail['perm']) {
            return $this->error('free-subscribe-fail');
        }

        $payment = $this->subscribeColumn($member,$column,$subPermDetail['payment_type'],$subPermDetail['expire_time']);// save payment
        
        event(new SubscribeEvent(request('column_id'),'column',$this->shop['id'], $this->member['id'], $subPermDetail['payment_type']));// 4 免费订阅
        
        $this->saveContentId();

        $expireTime = $payment->expire_time ? hg_format_date($payment->expire_time):null;
        return $this->output(['success'=>1,'expire_time'=>$expireTime]);
    }

    public function subScribeFreeCommonContent(){
        $this->validateWithAttribute(['content_id'=>'required'], ['content_id'=>'内容id']);

        $content = Content::where(['shop_id'=>$this->shop['id'],'hashid'=>request('content_id')])->whereIn('type',['article','video','audio','live'])->firstOrFail();

        $member = Member::where(['shop_id'=>$this->shop['id'],'uid'=>$this->member['id']])->first();

        $subed = $this->checkCommonContentSubscribe($content->type,$content->hashid);
        if ($subed) {
            return $this->error('already-subscribed');
        }

        $subPermDetail = $this->checkFreeSubscribePermission($member, $content->price, $content->join_membercard);
        
        if (!$subPermDetail['perm']) {
            return $this->error('free-subscribe-fail');
        }

        $payment = $this->subscribeCommonContent($member, $content, $subPermDetail['payment_type'], $subPermDetail['expire_time']);
        
        event(new SubscribeEvent(request('cotent_id'),$content->type, $this->shop['id'], $this->member['id'], $subPermDetail['payment_type']));

        $expireTime = $payment->expire_time ? hg_format_date($payment->expire_time):null;
        return $this->output(['success'=>1,'expire_time'=>$expireTime]);
    }

    private function saveContentId(){
        $content_id = Column::where(['column.hashid'=>request('column_id'),'column.shop_id'=>$this->shop['id']])->leftJoin('content','content.column_id','=','column.id')->where('content.payment_type',1)->pluck('content.hashid')->toArray();
        if($content_id){
            Redis::sadd('subscribe:applet:'.$this->shop['id'].':'.$this->member['id'],$content_id);
            Redis::sadd('subscribe:h5:'.$this->shop['id'].':'.$this->member['id'],$content_id);
        }
    }

    private function subscribeColumn($member,$column,$paymentType,$expireTime){
        $payment = Payment::freeSubscribeContent($member,'column',$column,$paymentType,$expireTime);
        return $payment;
    }

    /**
     * 订阅普通内容
     *
     * @return Payment;
     */
    private function subscribeCommonContent($member,$content,$paymentType,$expireTime){
        $payment = Payment::freeSubscribeContent($member, $content->type, $content, $paymentType, $expireTime);
        return $payment;
    }

    /**
     * 获取订阅数据
     * @return array
     */
    private function getSubscribeColumnList(){
        $count = request('count') ? : 20;
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        $payments = Payment::whereIn('user_id',$member_ids)->whereNotIn('content_type',['member_card','community'])->where(['shop_id'=>$this->shop['id']]);
        $payments->where(function($query){
            $query->where('expire_time',0)->orWhere('expire_time','>',time());
        });
        $payments =  $payments->orderby('order_time','desc')->groupBy(['content_id','content_type'])->paginate($count);
        return $this->listToPage($payments);
    }

    /**
     * @return mixed
     * 播放量/完播量加1
     */
    public function playCount(){
        $this->validateWithAttribute(['content_id'=>'required','sign'=>'required'],['content_id'=>'内容id','sign'=>'播放标识']);

        $job = (new ContentPlayCount($this->shop['id'],request('sign'),request('content_id')))->onQueue(DEFAULT_QUEUE);
        dispatch($job);
        return $this->output(['success'=>1]);
    }

    /**
     * @return mixed
     * 分享量加1
     */
    public function shareCount(){
        $this->validateWithAttribute(['content_id'=>'required'],['content_id'=>'内容id']);

        $job = (new ContentPlayCount($this->shop['id'],'share',request('content_id')))->onQueue(DEFAULT_QUEUE);
        dispatch($job);

        return $this->output(['success'=>1]);
    }

    /**
     * 处理列表返回值
     * @param $data
     * @return mixed
     */
    private function getSubscribeColumnListResponse($data){
        if($data['data']){
            foreach ($data['data'] as $key=>$item) {
                if($item->content_type == 'live' &&  $item->alive){
                    $item->alive->start_time > time() && $item->live_state = 0;
                    $item->alive->start_time < time() && $item->alive->end_time > time() && $item->live_state = 1;
                    $item->alive->end_time < time() && $item->live_state = 2;
                    $item->start_time = $item->alive?hg_format_date($item->alive->start_time):'';
                    $item->end_time = $item->alive?hg_format_date($item->alive->end_time):'';
                    $item->indexpic = $item->live_indexpic = hg_unserialize_image_link($item->alive->live_indexpic);
                    $item->brief =  $item->alive->brief ? htmlspecialchars_decode(str_ireplace('&nbsp;','',strip_tags($item->alive->brief))) : '';
                    $item->title = $item->content_title;
                    $item->type = $item->content_type;
                    $item->update_time = $item->content?($item->content->update_time ? date('m-d',$item->content->update_time) : ''):'';
                    $item->comment_count = $item->alive->comment_count?:0;
                    $item->view_count = $item->alive->view_count?$this->formatMultiple('view',$item->alive->view_count):0;
                    $item->makeHidden(['content_indexpic']);
                }elseif($item->content_type == 'column' && $item->column){
                    $item->title = $item->column->title;
                    $item->brief =  $item->column->brief ? htmlspecialchars_decode(str_ireplace('&nbsp;','',strip_tags($item->column->brief))) : '';
                    $item->content_id = $item->column->hashid;
                    $item->column_id = $item->column->id?intval($item->column->id):0;
                    $item->update_time = $item->column->update_time ? date('m-d',$item->column->update_time) : '';
                    $item->up_time = $item->column->up_time ? date('Y-m-d H:i:s',$item->column->up_time) : '';
                    $item->indexpic = hg_unserialize_image_link($item->column->indexpic);
                    $item->type = $item->content_type;
                    $item->comment_count = $item->column->comment_count?:0;
                    $item->view_count = $item->column->view_count?$this->formatMultiple('view', $item->column->view_count):0;
                    $item->subscribe = $item->column->subscribe?$this->formatMultiple('subscribe',$item->column->subscribe):0;
                    $item->stage = Content::where('column_id',$item->column->id)->where('type','!=','column')->where('state','<',2)->where('up_time','<',time())->count('id')?:0;
                    $item->makeHidden(['create_time','content','id','column','content_indexpic']);
                }elseif($item->content_type == 'course' && $item->course){
                    $item->title = $item->course->title;
                    $item->brief =  $item->course->brief ? htmlspecialchars_decode(str_ireplace('&nbsp;','',strip_tags($item->course->brief))) : '';
                    $item->content_id = $item->course->hashid;
                    $item->update_time = $item->course->update_time ? date('m-d',$item->course->update_time) : date('m-d',$item->course->create_time);
                    $item->up_time = $item->course->up_time ? date('Y-m-d H:i:s',$item->course->up_time) : '';
                    $item->makeHidden(['create_time','content','id','content_indexpic']);
                    $item->indexpic = hg_unserialize_image_link($item->course->indexpic);
                    $item->type = $item->content_type;
                    $item->comment_count = $item->course->comment_count?:0;
                    $item->view_count = $item->course->view_count?$this->formatMultiple('view',$item->course->view_count):0;
                    $item->subscribe = $item->course->subscribe?$this->formatMultiple('subscribe',$item->course->subscribe):0;
                    $item->pay_type = $item->course->pay_type?:'';
                    $item->hour_count = ClassContent::where('course_id',$item->course->hashid)->count('id');
                }elseif($item->content){
                    $item->title = $item->content->title;
                    $item->brief =  $item->content?($item->content->brief ? htmlspecialchars_decode(str_ireplace('&nbsp;','',strip_tags($item->content->brief))) : ''):'';
                    $item->content_id = $item->content?$item->content->hashid:'';
                    $item->column_id = $item->content?($item->content->column_id?intval($item->content->column_id):0):0;
                    $item->update_time = $item->content?($item->content->update_time ? date('m-d',$item->content->update_time) : ''):'';
                    $item->up_time = $item->content?($item->content->up_time ? date('Y-m-d H:i:s',$item->content->up_time) : ''):'';
                    $item->makeHidden(['create_time','content','id','content_indexpic']);
                    $item->indexpic = $item->content?hg_unserialize_image_link($item->content->indexpic):[];
                    $item->type = $item->content_type;
                    $item->comment_count = $item->content?($item->content->comment_count?:0):0;
                    $item->view_count = $item->content?($item->content->view_count?$this->formatMultiple('view',$item->content->view_count):0):0;
                }else{
                    $item->title = $item->content_title;
                    $item->brief =  '';
                    $item->column_id = 0;
                    $item->update_time = $item->order_time ? date('m-d',$item->order_time) :'';
                    $item->up_time = $item->order_time  ? date('Y-m-d H:i:s',$item->order_time ) :'';
                    $item->makeHidden(['create_time','content','id','content_indexpic']);
                    $item->indexpic = $item->content_indexpic?hg_unserialize_image_link($item->content_indexpic):[];
                    $item->type = $item->content_type;
                    $item->comment_count = 0;
                    $item->view_count = 0;
//                    unset($data['data'][$key]);
                }
                //获取内容的上下架状态，前端根据此字段判断是否显示该内容，0-未上架，1-上架，2-下架
                $item->state = $item->content ? $item->content->state : 1;
                $item->expire_time = $item->expire_time ? hg_format_date($item->expire_time) : null;
            }
        }
        return $data;
    }


    private function getSubscribeColumnListResponseOld($data){
        if($data['data']){
            foreach ($data['data'] as $item) {
                if($item->type == 'live' &&  $item->alive){
                    $item->alive->start_time > time() && $item->live_state = 0;
                    $item->alive->start_time < time() && $item->alive->end_time > time() && $item->live_state = 1;
                    $item->alive->end_time < time() && $item->live_state = 2;
                    $item->live_indexpic = hg_unserialize_image_link($item->alive->live_indexpic);
                }
                $item->brief =  $item->brief ? htmlspecialchars_decode(str_ireplace('&nbsp;','',strip_tags($item->brief))) : '';
                $item->content_id = $item->hashid;
                $item->column_id = $item->column_id?intval($item->column_id):0;
                $item->update_time = $item->update_time ? date('m-d',$item->update_time) : '';
                $item->up_time = $item->up_time ? date('Y-m-d H:i:s',$item->up_time) : '';
                $item->makeHidden(['create_time','content','id']);
                $item->indexpic = hg_unserialize_image_link($item->indexpic);
            }
        }
        return $data;
    }



    private function selectFreeDetail($id){
        $content = Content::where(['hashid'=>$id,'shop_id'=>$this->shop['id']])
            ->select('id', 'hashid','title','indexpic','update_time','type','payment_type','column_id','price','brief','comment_count','view_count','is_lock','state','up_time', 'join_membercard')
            ->firstOrfail();
        if($content->is_lock){
            return $this->error('content_locked');
        }
        //判断内容上下架状态
        if($content->state == 2){
            $this->error('off-shelf');
        }
        if($content->up_time > time() && $content->state == 0){
            $this->error('not-on-shelf');
        }
        if($content->column_id!=0){
            $column = $content->column;
            $content->column_title = $column->title;
            $content->column_price = $column->price;
            $content->column_finish = $column->finish;
            $content->column_indexpic =hg_unserialize_image_link($column->indexpic);
            $content->column_subscribe = $column->subscribe;
            $content->column_stage = count($column->content->where('type','!=','column')->where('state','<',2)->where('up_time','<',time()));
        }
        if($content->type=='audio'){
            $content->test_size = $content->audio?$content->audio->test_size:0;
            $content->test_url = $content->audio?$content->audio->test_url:'';
            $content->test_file_name = $content->audio?$content->audio->test_file_name:'';

        }
        if($content->type=='video'){
            $content->patch   = $content->video?hg_unserialize_image_link($content->video->patch):[];
            $content->file_id = $content->video?$content->video->file_id:'';
            $content->file_name = $content->video?$content->video->file_name:'';
            $content->size = $content->video?$content->video->size:0;
            $content->test_file_id = $content->video?$content->video->test_file_id:'';
            $content->test_file_name = $content->video?$content->video->test_file_name:'';
            $content->test_size = $content->video?$content->video->test_size:0;
            $content->test_video_path = $content->video?($content->video->testVideoInfo ? $this->getVideoUrl($content->video->testVideoInfo) : ''):'';
            $content->ratio = $this->getRatio($content->video->file_id);
            $content->test_ratio = $content->video->test_file_id ? $this->getRatio($content->video->test_file_id) : '';
        }
        if($content->type=='live'){
            if($content->alive){
                $content->alive->start_time > time() && $content->live_state = 0;
                $content->alive->start_time < time() && $content->alive->end_time > time() && $content->live_state = 1;
                $content->alive->end_time < time() && $content->live_state = 2;
            }
            $content->countdown = $content->alive?$content->alive->start_time - time():'';
            $content->now_time = time();
            $content->start_time = $content->alive?date('Y-m-d H:i:s',$content->alive->start_time):'';
            $content->end_time = $content->alive?date('Y-m-d H:i:s',$content->alive->end_time):'';
            $content->brief = $content->alive?($content->alive->brief?htmlspecialchars_decode(str_ireplace('&nbsp;','',strip_tags($content->alive->brief))):''):'';
            $content->live_indexpic = $content->alive?hg_unserialize_image_link($content->alive->live_indexpic):'';
            $content->live_describe = $content->alive?$content->alive->live_describe:'';
            $content->live_flow = $content->alive?$content->alive->live_flow:'';
            $content->gag = $content->alive?$content->alive->gag:0;
            $content->manage = $content->alive?$content->alive->manage:0;
            $content->new_live = $content->alive?$content->alive->new_live:0;
            $person_id = $content->alive?array_pluck(json_decode($content->alive->live_person, true),'id'):'';
            $content->lecturer = $content->alive?(in_array($this->member['id'],$person_id) ? 1 : 0):0;
            $content->cover_url = $content->alive?($content->alive->videoInfo ? $content->alive->videoInfo->cover_url:hg_unserialize_image_link(config('define.default_pic'))):'';
            $content->comment_count = AliveMessage::where(['shop_id'=>$this->shop['id'],'content_id'=>$content->hashid,'is_del'=>0])->count()?:0;
        }
        return $content;
    }

    private function selectFreeColumnDetail($id){
        $detail = Column::where(['hashid'=>$id,'shop_id'=>$this->shop['id']])
            ->select('id','hashid','title','indexpic','brief','describe','update_time','price','state','charge','finish','join_membercard', 'mainpic')
            ->firstOrFail();
        return $detail;
    }

    private function getColumnContent(){
        $id = Column::where(['hashid'=>request('column_id'),'shop_id'=>$this->shop['id']])->value('id');
        $where = ['column_id'=>$id,'shop_id'=>$this->shop['id']];
        trim(request('source')) == 'wx_applet' && $where['payment_type'] = 1;
        $lists = Content::where($where)
            ->where(function ($query) {
                $query->where('state',1)->orWhere('state',0);
            })
            ->where('up_time','<', time())
            ->whereNotIn('type',['column','course'])
            ->orderBy('column_order_id','asc')
            ->orderBy('up_time','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->paginate(request('count')?:5);
        return $lists;
    }

    private function selectColumn(){
        $where = ['state'=>1,'display'=>1,'column.shop_id'=>$this->shop['id']];
        if(trim(request('source'))=='wx_applet'){
            $where['charge'] = 0;
            $where['price'] = 0.00;
        }
        $sql = Column::where($where);
        $filters = $this->contentCommonFilters();
        $sql = $this->filterSql($sql, $filters);
        request('type_id') && $sql->join('content_type','content_type.content_id','=','column.hashid')
            ->where('content_type.type_id',request('type_id'))
            ->select('column.*');
        $column = $sql->orderBy('column.order_id')
            ->orderBy('column.top','desc')
            ->orderBy('column.update_time','desc')
            ->paginate(request('count')?:5);
        return $column;
    }

    private function selectAlive(){
        $filters = $this->contentCommonFilters();
        $sql = Content::join('live','live.content_id','=','content.hashid');
        $sql = $this->filterSql($sql, $filters);
        $live = $sql->where(['content.type'=>'live','content.display'=>1,'content.shop_id'=>$this->shop['id']])
            ->where(function ($query) {
                $query->where('content.state',1)->orWhere('content.state',0);
            })
            ->where('content.payment_type', '!=', 1)
            ->where('content.up_time' ,'<', time())
            ->orderBy('order_id')
            ->orderBy('up_time','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->paginate(request('count')?:5);
        return $live;
    }

    private function selectContent(){
        $types = ['audio','video','article'];
        $not_select_pay_type = [1,4];
        $where = ['display'=>1,'shop_id'=>$this->shop['id']];
        trim(request('source')) == 'wx_applet' && $where['payment_type'] = 3;
        $sql = Content::where($where);
        $filters = $this->contentCommonFilters();
        $content_type_filters = $this->contentTypeFilters();
        if (is_array($filters) && is_array($content_type_filters)){
            $content_filters = array_merge($filters, $content_type_filters);
            $sql = $this->filterSql($sql, $content_filters);
        }
        $type = request('type');
        if(isset($type)){
            $sql->where('type',$type);
        }else{
            $sql->whereIn('type',$types);
        }
        $content = $sql->where(function ($query) {
                $query->where('state',1)->orWhere('state',0);
            })
            ->where('up_time','<', time())
            ->whereNotIn('payment_type', $not_select_pay_type)
            ->orderBy('order_id')
            ->orderBy('up_time','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->paginate(request('count')?:8);
        return $content;
    }

    private function selectColumnDetail($id){
        $detail = Column::where(['hashid'=>$id,'shop_id'=>$this->shop['id']])->firstOrFail();
        if($detail->is_lock){
            return $this->error('content_locked');
        }
        return $detail;
    }

    private function formatColumnDetail($data){
        $return = [];
        if($data){
            if(intval($data->state) == 0){
                $this->error('off-shelf');
            }
            $data->column_id = $data->hashid;
            $data->update_time = $data->update_time?date('Y-m-d',$data->update_time):0;
            $data->type_id = $data->column_type->pluck('type_id');
            $data->stage = count($data->content->where('type','!=','column')->where('state','<',2)->where('up_time','<',time()));
            $data->type = 'column';
            $original_price = $data->price;
            $data->promoter = $this->promoter($this->shop['id'],$this->member['id'],$data->hashid,$data->type,$original_price);
            $data->price = $this->getDiscountPrice($data->price,$data->hashid,$data->type,boolVal($data->join_membercard));
            if( $original_price != $data->price) {
                $data->cost_price = $original_price;
            }
            if( $limit = $this->limitPurchase($original_price,$data->hashid,$data->type)){
                $data->market_sign = $limit['market_sign'];
                $data->limit_start = $limit['limit_start'];
                $data->limit_end = $limit['limit_end'];
                $data->limit_state = $limit['limit_state'];
                $data->limit_id = $limit['limit_id'];
                $data->limit_price = $limit['limit_price'];
            }
            // 拼团信息
            $fg = $this->contentFightGroup($this->shopInstance->id,$this->shopInstance->hashid,'column',$data->hashid);
            $data->fightgroup = $fg ? $fg->id:null;
            
            $data->indexpic = hg_unserialize_image_link($data->indexpic);
            $data->mainpic = hg_unserialize_image_link($data->mainpic);
            unset($data->content);
            unset($data->column_type);
            $data->is_subscribe = intval($this->checkColumnSubscribe($data->hashid));
            $data->pay_type = intval($data->charge) ? 1 : 0;
            $data->finish = intval($data->finish);
            isset($data->subscribe) && $data->subscribe = $this->formatMultiple('subscribe',$data->subscribe);
            $data->remind = ShopContentRemind::where([
                'shop_id'=>$this->shop['id'],
                'source'=>$this->member['source'],
                'content_id'=>$data->hashid,
                'content_type'=>'column',
                'openid'=>$this->member['openid']])->value('push_status')?:0;
            $return['data'] = $data;
        }
        return $return?:[];
    }

    private function checkColumnSubscribe($column_id)
    {
        return $this->checkProductPayment('column',$column_id);
    }

     private function checkCommonContentSubscribe($content_type,$content_id)
    {
        return $this->checkProductPayment($content_type,$content_id);
    }

    private function checkContentSubscribe($content_id)
    {
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids) {
            $subscribe = Payment::whereIn('user_id', $member_ids)
                ->where(['content_id' => $content_id])
                ->whereIn('content_type', ['article', 'audio', 'video', 'live'])
                ->first();
        }else {
            $subscribe = Payment::where(['user_id'=>$this->member['id'], 'content_id' => $content_id])
                ->whereIn('content_type', ['article', 'audio', 'video', 'live'])
                ->first();
        }
        return $subscribe ? 1 : 0;
    }

    private function formatColumn($data){
        if($data){
            $shopHighestMembercard = $this->shopHighestDiscountMembercard();
            foreach ($data as $k=>$v){
                $v->column_id = $v->hashid;
                $v->create_time = $v->create_time?date('Y-m-d',$v->create_time):0;
                $v->update_time = $v->update_time?date('Y-m-d',$v->update_time):0;
                if(request('source')=='wx_applet'){
                    $v->stage = count($v->content->where('payment_type',1)->where('type','!=','column')->where('state','<',2)->where('up_time','<',time()));
                }else{
                    $v->stage = count($v->content->where('type','!=','column')->where('state','<',2)->where('up_time','<',time()));
                }
                $v->indexpic = hg_unserialize_image_link($v->indexpic);
                $v->subscribe = $this->formatMultiple('subscribe',$v->subscribe);
                $v->market_sign = MarketingActivity::where(['shop_id'=>$this->shop['id'],'content_id'=>$v->hashid,'content_type'=>'column'])->value('marketing_type')?:'';
                $v->membercard_discount = $this->shopHighestDiscount($shopHighestMembercard, $v->join_membercard);
                unset($v->content);
            }
        }
        return $data?:[];
    }

    private function formatContent($data){
        if($data){
            $shopHighestMembercard = $this->shopHighestDiscountMembercard();
            foreach ($data as $k=>$v){
                if($v->type=='live'){
                    $start_time = $v->alive->start_time;
                    $end_time = $v->alive->end_time;
                    $start_time > time() && $v->live_state = 0;
                    $start_time < time() && $end_time > time() && $v->live_state = 1;
                    $end_time < time() && $v->live_state = 2;
                    $v->live_indexpic = hg_unserialize_image_link($v->live_indexpic);
                    $v->comment_count = AliveMessage::where(['shop_id'=>$this->shop['id'],'content_id'=>$v->hashid,'is_del'=>0])->count()?:0;
                }
                if($v->column_id!=0){
                    $column = $v->column;
                    $v->column_title = $column->title;
                    $v->column_price = $column->price;
                    $v->column_charge = intval($column->charge) ? 1 : 0;
                    $v->column_finish = intval($column->finish);
                    $v->column_indexpic =hg_unserialize_image_link($column->indexpic);
                    $v->column_subscribe = intval($column->subscribe);
                    $v->column_stage = count($column->content->where('type','!=','column')->where('state','<',2)->where('up_time','<',time()));
                }
                $v->content_id = $v->hashid;
                $v->create_time = $v->create_time?date('m-d',$v->create_time) : 0;
                $v->update_time = $v->update_time?date('m-d',$v->update_time) : 0;
                $v->up_time = $v->up_time?date('Y-m-d H:i:s',$v->up_time) : 0;
                $v->start_time && $v->start_time = date('Y-m-d H:i:s',$v->start_time);
                $v->end_time && $v->end_time = date('Y-m-d H:i:s',$v->end_time);
                $v->brief && $v->brief = htmlspecialchars_decode(str_ireplace('&nbsp;','',mb_substr(strip_tags($v->brief),0,100)));
                $v->column_id = $v->column_id?intval($v->column_id):0;
                $v->indexpic = hg_unserialize_image_link($v->indexpic);
                $v->view_count = $this->formatMultiple('view',$v->view_count);
                $v->is_test = intval($v->is_test) ? 1 : 0;
                $v->market_sign = MarketingActivity::where(['shop_id'=>$this->shop['id'],'content_id'=>$v->hashid,'content_type'=>$v->type])->value('marketing_type')?:'';
                $v->membercard_discount = $this->shopHighestDiscount($shopHighestMembercard, $v->join_membercard);
                Cache::forget('content:'.$this->shop['id'].':'.$v->hashid);
            }
        }
        return $data?:[];
    }

    private function formatDetail($data){
        $return = [];
        if($data){
            $promoterStatus = PromotionContent::where(['shop_id'=>$this->shop['id'],'content_id'=>$data->hashid,'content_type'=>$data->type])->value('id');
            $data->column_id = intval($data->column_id)!= 0 ? $data->column->hashid : 0;
            $data->content_id = $data->hashid;
            $original_price = $data->price;
            $data->promoter = $this->promoter($this->shop['id'],$this->member['id'],$data->hashid,$data->type,$original_price);
            $data->price = $this->getDiscountPrice($data->price,$data->hashid,$data->type, boolVal($data->join_membercard));
            $price = $data->price;
            if( $original_price != $data->price) {
                $data->cost_price = $original_price;
            }
            $data->market_sign = isset($price['market_sign'])?$price['market_sign']:'';
            if( $limit = $this->limitPurchase($original_price,$data->hashid,$data->type)){
                $data->market_sign = $limit['market_sign'];
                $data->limit_start = $limit['limit_start'];
                $data->limit_end = $limit['limit_end'];
                $data->limit_state = $limit['limit_state'];
                $data->limit_id = $limit['limit_id'];
                $data->limit_price = $limit['limit_price'];
            }
            $fg = $this->contentFightGroup($this->shopInstance->id,$this->shopInstance->hashid,$data->type,$data->hashid);
            $data->fightgroup = $fg ? $fg->id:null;

            $data->update_time = $data->update_time?date('Y-m-d',$data->update_time):0;
            $data->indexpic = hg_unserialize_image_link($data->indexpic);
            $data->is_subscribe = intval($this->checkCommonContentSubscribe($data->type,$data->hashid));
            isset($data->subscribe) && $data->subscribe = $this->formatMultiple('subscribe',$data->subscribe);
            $data->view_count = $this->formatMultiple('view',$data->view_count);
            $data->promoter_status = $promoterStatus ? true : false;
            $data->remind = ShopContentRemind::where(['shop_id'=>$this->shop['id'],'source'=>$this->member['source'],'content_id'=>$data->hashid,'content_type'=>$data->type,'openid'=>$this->member['openid']])->value('push_status')?:0;
            $return['data'] = $data;
        }
        return $return?:[];
    }

    private function selectDetail($id){
        $content = Content::where(['hashid'=>$id,'shop_id'=>$this->shop['id']])->firstOrfail();
        if($content->is_lock){
            return $this->error('content_locked');
        }
        //判断内容上下架状态
        if($content->state == 2){
            $this->error('off-shelf');
        }
        if($content->up_time > time() && $content->state == 0){
            $this->error('not-on-shelf');
        }
        if($content->column_id!=0){
            $column = $content->column;
            $content->column_title = $column?$column->title:'';
            $content->column_price = $column?$column->price:'';
            $content->column_finish = $column?$column->finish:'';
            $content->column_indexpic = $column?hg_unserialize_image_link($column->indexpic):[];
            $content->column_subscribe = $column?$column->subscribe:'';
            $content->column_stage = $column?count($column->content->where('type','!=','column')->where('state','<',2)->where('up_time','<',time())):0;
        }
        switch ($content->type){
            case 'article':
                $content->content = $content->article?$content->article->content:'';break;
            case 'audio':
                $content->content = $content->audio->content ? : $content->brief;
                $content->file_name = $content->audio?$content->audio->file_name:'';
                $content->test_file_name = $content->audio?$content->audio->test_file_name:'';
                $content->url = $content->audio?$content->audio->url:'';
                $content->test_url = $content->audio?$content->audio->test_url:'';
                $content->size = $content->audio?$content->audio->size:0;
                $content->test_size = $content->audio?$content->audio->test_size:0;
                break;
            case 'video':
                $content->content = $content->video->content ? : $content->brief;
                $content->patch   = $content->video?hg_unserialize_image_link($content->video->patch):'';
                $content->file_id = $content->video?$content->video->file_id:'';
                $content->file_name = $content->video?$content->video->file_name:'';
                $content->size = $content->video?$content->video->size:0;
                $content->test_file_id = $content->video?$content->video->test_file_id:'';
                $content->test_file_name = $content->video?$content->video->test_file_name:'';
                $content->test_size = $content->video?$content->video->test_size:0;
                $content->test_video_path = $content->video?($content->video->testVideoInfo ? $this->getVideoUrl($content->video->testVideoInfo) : ''):'';
                $content->video_path = $content->video?($content->video->videoInfo?$this->getVideoUrl($content->video->videoInfo):''):'';
                $content->ratio = $this->getRatio($content->video->file_id);
                $content->test_ratio = $content->video->test_file_id ? $this->getRatio($content->video->test_file_id) : '';
                break;
            case 'live' :
                $content->brief = $content->alive?($content->alive->brief?htmlspecialchars_decode(str_ireplace('&nbsp;','',strip_tags($content->alive->brief))):''):'';
                $content->live_indexpic = $content->alive?hg_unserialize_image_link($content->alive->live_indexpic):[];
                $content->new_live = $content->alive?$content->alive->new_live:0;
                $content->live_type = $content->alive?$content->alive->live_type:'';
                $content->live_flow = $content->alive?$content->alive->live_flow:'';
                $content->file_id = $content->alive?$content->alive->file_id:'';
                $content->video_path = $content->alive?($content->alive->videoInfo ? $this->getVideoUrl($content->alive->videoInfo) : ''):'';
                $content->cover_url = $content->alive?($content->alive->videoInfo ? $content->alive->videoInfo->cover_url:hg_unserialize_image_link(config('define.default_pic'))):'';
                $content->alive && $content->alive->start_time > time() && $content->live_state = 0;
                if($content->alive && $content->alive->start_time > time()){
                    $content->live_state = 0;
                    Redis::sadd('subscribe:h5:'.$this->shop['id'].':'.$this->member['id'],$content->hashid);
                    $job = (new SubscribeForget($content,$this->member['id']))->onQueue(DEFAULT_QUEUE)->delay(($content->alive->start_time-time())/60);
                    dispatch($job);
                }
                $content->alive && $content->alive->start_time < time() && $content->alive->end_time > time() && $content->live_state = 1;
                $content->alive && $content->alive->end_time < time() && $content->live_state = 2;
                $content->now_time = time();
                $content->countdown = $content->alive?$content->alive->start_time - time():'';
                $content->start_time = $content->alive?date('Y-m-d H:i:s',$content->alive->start_time):'';
                $content->end_time = $content->alive?date('Y-m-d H:i:s',$content->alive->end_time):'';
                $content->live_describe = $content->alive?$content->alive->live_describe:'';
                $content->gag = $content->alive?$content->alive->gag:0;
                $content->manage = $content->alive?$content->alive->manage:0;
                $content->live_person = $content->alive?json_decode($content->alive->live_person, true):[];
                $content->type_id = $content->content_type?$content->content_type->pluck('type_id'):[];
                $person_id = $content->alive?array_pluck(json_decode($content->alive->live_person, true),'id'):[];
                $content->lecturer = in_array($this->member['id'],$person_id) ? 1 : 0;
                $content->comment_count = AliveMessage::where(['shop_id'=>$this->shop['id'],'content_id'=>$content->hashid,'is_del'=>0])->count()?:0;
                $content->obs_flow = $content->alive?($content->alive->obs_flow ? unserialize($content->alive->obs_flow) : []):[];
                if($content->obs_flow){
                    $obs_flow = $content->obs_flow;
                    if(isset($obs_flow['obs_url'])){
                        $obs_flow['obs_url'] = str_replace('http://', 'https://', $obs_flow['obs_url']);
                        $content->obs_flow = $obs_flow;
                    }
                }
                break;
        }
        return $content;
    }

    /**
     * 优先级 自定义高清>高清>标清>原视频
     *
     * @param $videos
     * @return mixed
     */
    private function getVideoUrl($videos){
        $data = unserialize($videos->play_set);
        if($data) {
            //原视频
            $original_url = '';
            //标清 640w_512kbps
            $sd_url = '';
            //高清 1280w_1024kbps
            $hd_url = '';
            //自定义高清 1280w_512kbps
            $custom_hd_url = '';
            foreach ($data as $item) {
                $definition = $item['definition'];
                if ($definition == config('qcloud.vod.original_definition')){
                    $original_url = $item['url'];
                } else if ($definition == config('qcloud.vod.hls_sd_definition')){
                    $sd_url = $item['url'];
                } else if ($definition == config('qcloud.vod.hls_hd_definition')){
                    $hd_url = $item['url'];
                } else if ($definition == config('qcloud.vod.custom_hd_definition')){
                    $custom_hd_url = $item['url'];
                }
            }
            if ($custom_hd_url) {
                $url = $custom_hd_url;
            } else if($hd_url){
                $url = $hd_url;
            }  else if($sd_url){
                $url = $sd_url;
            }  else {
                $url = $original_url;
            }
            return str_replace('1253562005.vod2.myqcloud.com','dianbo.duanshu.com',$url);
        }
    }


    public function getTypeContentNew()
    {
        $this->validateWith([
            'count'    =>  'numeric',
            'type_id'  =>  'required|numeric'
        ]);
        $count = request('count') ? : 8;

        //筛选上架的，非专栏外单卖的内容
        $search = ContentType::where('content.shop_id',$this->shop['id'])
            ->where('type_id',request('type_id'))
            ->where('content.up_time','<',time())
            ->where('content.state','!=',2)
            ->where('content.payment_type','!=',4)
            ->Join('content', function ($join) {
                $join->on('content_type.content_id', '=', 'content.hashid')
                    ->on('content_type.type', '=', 'content.type');
            })
            ->select('content.*');
        $version = Shop::where('hashid',$this->shop['id'])->value('applet_version');
        if(request('source') == 'wx_applet' && $version == 'basic'){
            $search = $search->where('content.price','==','0.00');
        }
        $content = $search->orderBy('content_type.order_id','asc')->orderBy('content_type.create_time','desc')->paginate($count);
        if($content && $content->count() > 0){
            foreach ($content->items() as $item){
                if($item->type == 'column'){
                    $columnIds[] = $item->hashid;
                }
                if($item->type == 'course'){
                    $courseIds[] = $item->hashid;
                }
                if($item->type == 'live'){
                    $liveIds[] = $item->hashid;
                }
            }
        }
        if(isset($columnIds)){
            $column = Column::getColumnByIds($columnIds);
            if($column){
                foreach ($column as $v){
                    $columns[$v['hashid']] = $v;
                }
            }
        }
        if(isset($courseIds)){
            $course = Course::getCourseByIds($courseIds);
            if($course){
                foreach ($course as $v){
                    $courses[$v['hashid']] = $v;
                }
            }
        }
        if(isset($liveIds)){
            $live = Alive::getVidepByIds($liveIds);
            if($live){
                foreach ($live as $v){
                    $lives[$v['content_id']] = $v;
                }
            }
        }
        if($content && $content->count() > 0){
            foreach ($content->items() as $item){
                if($item->type == 'column' && isset($columns)){
                    $item->charge = $columns[$item->hashid]->charge;
                    $item->finish = $columns[$item->hashid]->finish;
                    $item->stage = Content::where('column_id',$columns[$item->hashid]->id)->where('type','!=','column')->where('state','<',2)->where('up_time','<',time())->count('id')?:0;
                    $item->subscribe = $columns[$item->hashid]->subscribe;
                    $item->is_display = 1;
                }
                if($item->type == 'course' && isset($courses)){
                    $item->pay_type = $courses[$item->hashid]->pay_type;
                    $item->is_finish = $courses[$item->hashid]->is_finish;
                    $item->course =  $courses[$item->hashid];
                    $item->is_display = $courses[$item->hashid]->is_display;
                    $item->hour_count = ClassContent::where('course_id',$item->hashid)->count('id');
                    $item->subscribe = $courses[$item->hashid]->subscribe;
                    $item->is_display = 1;
                }
                if($item->type == 'live' && isset($lives)){
                    $lives[$item->hashid]->start_time > time() && $item->live_state = 0;
                    $lives[$item->hashid]->start_time < time() && $lives[$item->hashid]->end_time > time() && $item->live_state = 1;
                    $lives[$item->hashid]->end_time < time() && $item->live_state = 2;
                    $item->is_display = 1;
                }

                $item->content_id = $item->hashid;
                $item->subscribe = $this->formatMultiple('subscribe',$item->subscribe);
                $item->indexpic = hg_unserialize_image_link($item->indexpic);
                $item->create_time = hg_format_date($item->create_time);
                $item->update_time = date('m-d',$item->update_time);
                $item->brief = htmlspecialchars_decode(str_ireplace('&nbsp;','',mb_substr(strip_tags($item->brief),0,100))) ;
                $item->view_count = $this->formatMultiple('view',intval($item->view_count));
                $item->comment_count = intval($item->comment_count);
                $item->is_display = $item->is_display ? 1 : 0;
                if($item->payment_type != '1') $item->is_display = 1;
            }
        }
        $data = $this->listToPage($content);
        return $this->output($data);
    }

    /**
     * 通过导航id获取内容列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContentByType()
    {
        $this->validateWith([
            'count'    =>  'numeric',
            'type_id'  =>  'required|numeric'
        ]);
        $count = request('count') ? : 8;
        $content = ContentType::select('content_id','type')->where('type_id',request('type_id'))->orderBy('create_time','desc')->paginate($count);
        $view = Views::select(DB::raw('count(member_id) as view,content_id'))->groupBy('content_id')->pluck('view','content_id')->toArray();
        $comment = Comment::select(DB::raw('count(member_id) as comment,content_id'))->groupBy('content_id')->pluck('comment','content_id')->toArray();
        $data = $this->listToPage($content);
        if ($data && $data['data']) {
            foreach ($data['data'] as $key=> $item) {
                if ($item->type == 'column') {
                    if(request('source')=='wx_applet' && $item->belongToColumn->price!='0.00'){
                        unset($data['data'][$key]);
                        --$data['page']['total'];
                    }else{
                        $item->column_id = $item->belongToColumn ? $item->belongToColumn->id : 0;
                        $item->title = $item->belongToColumn ? $item->belongToColumn->title : '';
                        $item->price = $item->belongToColumn ? $item->belongToColumn->price : '';
                        $item->charge = $item->belongToColumn ? $item->belongToColumn->charge : '';
                        $item->finish = $item->belongToColumn ? $item->belongToColumn->finish : '';
                        $item->subscribe = $item->belongToColumn ? $this->formatMultiple('subscribe',$item->belongToColumn->subscribe) : '';
                        $item->indexpic = $item->belongToColumn ? hg_unserialize_image_link($item->belongToColumn->indexpic) : '';
                        $item->create_time = $item->belongToColumn ? hg_format_date($item->belongToColumn->create_time) : '';
                        $item->update_time = $item->belongToColumn ? date('m-d',$item->belongToColumn->update_time) : '';
                        $item->brief = $item->belongToColumn ? htmlspecialchars_decode(str_ireplace('&nbsp;','',mb_substr(strip_tags($item->belongToColumn->brief),0,100))) : '';
                        $item->view_count = array_key_exists($item->content_id,$view) ? $this->formatMultiple('view',intval($view[$item->content_id])) : 0;
                        $item->comment_count = array_key_exists($item->content_id,$comment) ? intval($comment[$item->content_id]) : 0;
                        $item->is_display = $item->belongToColumn ? ($item->belongToColumn->state == 1 ? 1 : 0) : 0;
                        $item->stage = $item->belongToColumn ? Content::where('column_id',$item->belongToColumn->id)->where('type','!=','column')->where('state','<',2)->where('up_time','<',time())->count('id') : 0;
                    }
                } elseif ($item->type == 'course') {
                    if(request('source')=='wx_applet'){
                        unset($data['data'][$key]);
                        --$data['page']['total'];
                    }else{
                        $item->title = $item->belongToCourse ? $item->belongToCourse->title : '';
                        $item->price = $item->belongToCourse ? $item->belongToCourse->price : '';
                        $item->pay_type = $item->belongToCourse ? $item->belongToCourse->pay_type : '';
                        $item->is_finish = $item->belongToCourse ? $item->belongToCourse->is_finish : '';
                        $item->subscribe = $item->belongToCourse ? $this->formatMultiple('subscribe',$item->belongToCourse->subscribe) : '';
                        $item->indexpic = $item->belongToCourse ? hg_unserialize_image_link($item->belongToCourse->indexpic) : '';
                        $item->create_time = $item->belongToCourse ? hg_format_date($item->belongToCourse->create_time) : '';
                        $item->update_time = $item->belongToCourse ? date('m-d',$item->belongToCourse->update_time) : '';
                        $item->brief = $item->belongToCourse ? htmlspecialchars_decode(str_ireplace('&nbsp;','',mb_substr(strip_tags($item->belongToCourse->brief),0,100))) : '';
                        $item->view_count = array_key_exists($item->content_id,$view) ? $this->formatMultiple('view',intval($view[$item->content_id])) : 0;
                        $item->comment_count = array_key_exists($item->content_id,$comment) ? intval($comment[$item->content_id]) : 0;
                        $item->course = $item->belongToCourse ? : [];
                        $item->is_display = $item->belongToCourse ? ($item->belongToCourse->state == 1 ? 1 : 0) : 0;
                        $item->hour_count = ClassContent::where('course_id',$item->content_id)->count('id');
                    }
                } else {
                    if(request('source')=='wx_applet' && ($item->belongToContent->payment_type == 2 || $item->belongToContent->payment_type == 4)){
                        unset($data['data'][$key]);
                        --$data['page']['total'];
                    }else{
                        if ($item->type == 'live') {
                            $item->belongToContent->start_time > time() && $item->live_state = 0;
                            $item->belongToContent->start_time < time() && $item->belongToContent->end_time > time() && $item->live_state = 1;
                            $item->belongToContent->end_time < time() && $item->live_state = 2;
                        }
                        $item->column_id = $item->belongToContent ? $item->belongToContent->column_id : 0;
                        $item->title = $item->belongToContent ? $item->belongToContent->title : '';
                        $item->price = $item->belongToContent ? $item->belongToContent->price : '';
                        $item->subscribe = $item->belongToContent ? $this->formatMultiple('subscribe',$item->belongToContent->subscribe) : '';
                        $item->column_id = $item->belongToContent ? $item->belongToContent->column_id : '';
                        $item->payment_type = $item->belongToContent ? $item->belongToContent->payment_type : '';
                        $item->indexpic = $item->belongToContent ? hg_unserialize_image_link($item->belongToContent->indexpic) : '';
                        $item->create_time = $item->belongToContent ? hg_format_date($item->belongToContent->create_time) : '';
                        $item->update_time = $item->belongToContent ? date('m-d',$item->belongToContent->update_time) : '';
                        $item->up_time = $item->belongToContent ? hg_format_date($item->belongToContent->up_time) : '';
                        $item->brief = $item->belongToContent ? htmlspecialchars_decode(str_ireplace('&nbsp;','',mb_substr(strip_tags($item->belongToContent->brief),0,100))) : '';
                        $item->view_count = $item->belongToContent ? $this->formatMultiple('view',$item->belongToContent->view_count) : 0;
                        $item->comment_count = array_key_exists($item->content_id,$comment) ? intval($comment[$item->content_id]) : 0;
                        $item->is_display = $item->belongToContent ? ($item->belongToContent->state == 2 ? 0 : ($item->belongToContent->state == 0 && $item->belongToContent->up_time > time() ? 0 : 1)) : 0;
                        $item->payment_type == 1 && $item->is_display = 0;
                        //专栏外单卖时需要显示单内容及专栏内容，故特殊处理
                        if($item->payment_type == 4 || $item->payment_type == 1){
                            $column = Column::find($item->column_id);
                            $item->column = [];
                            if($column && intval($column->state) == 1){
                                $column->column_id = $column->hashid;
                                $column->indexpic = $column->indexpic ? unserialize($column->indexpic) : [];
                                $column->update_time = $column->update_time ? date('m-d',$column->update_time) : '';
                                $column->charge = intval($column->cahrge);
                                $column->view_count = 0;
                                $column->comment_count = 0;
                                $column->stage = Content::where('column_id',$column->id)->where('type','!=','column')->where('state','<',2)->where('up_time','<',time())->count('id')?:0;
                                $item->column = $column;
                            }
                        }
                    }
                }
            }
//            sort($data['data']);
        }
        return $this->output($data);
    }

    //浏览量、订阅量、在线量倍数处理
    private function formatMultiple($range,$count){
        return hg_caculate_multiple($count,$range,$this->shop['id']);
    }

    private function getRatio($file_id)
    {
        $ratio = Videos::where('file_id',$file_id)->value('ratio');
        return $ratio ? unserialize($ratio) : '';
    }
}