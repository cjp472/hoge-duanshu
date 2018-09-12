<?php


namespace App\Http\Controllers\H5\Content;


use App\Events\ContentViewEvent;
use App\Events\SubscribeEvent;
use App\Jobs\CheckPaymentExpire;
use App\Jobs\CourseViewCount;
use App\Models\CardRecord;
use App\Models\ChapterContent;
use App\Http\Controllers\H5\BaseController;
use App\Models\ClassContent;
use App\Models\ClassViews;
use App\Models\Content;
use App\Models\Course;
use App\Models\Comment;
use App\Models\LimitPurchase;
use App\Models\MarketingActivity;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopContentRemind;
use App\Models\Videos;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class CourseController extends BaseController
{
    /**
     * 课程列表
     * @return mixed
     */
    public function lists(){
        $count = request('count') ? : 10;
        $version = Shop::where('hashid',$this->shop['id'])->value('applet_version');
        $where = ['shop_id' => $this->shop['id'], 'state' => 1, 'is_lock' => 0];
        if(request('source') == 'wx_applet' && $version == 'basic'){
            $where['pay_type'] = 0;
        }
        $sql = Course::where($where);
        $filters = $this->contentCommonFilters();
        $sql = $this->filterSql($sql, $filters);
        $course = $sql->select('id', 'hashid', 'shop_id', 'title', 'indexpic', 'subscribe', 'price', 'is_finish', 'pay_type', 'course_type', 'describe', 'brief', 'join_membercard')
            ->orderBy('order_id')
            ->orderBy('top', 'desc')
            ->orderBy('create_time', 'desc')
            ->paginate($count);
        $data = $this->listToPage($course);
        $shopHighestMembercard = $this->shopHighestDiscountMembercard();
        foreach ($data['data'] as $item) {
            $item->content_id = $item->hashid;
            $item->hour_count = $item->class_hour ? $item->class_hour->count() : 0;
            $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic ) : '';
            $item->subscribe =hg_caculate_multiple($item->subscribe,'subscribe',$this->shop['id']);
            $item->type = 'course';
            $item->market_sign = MarketingActivity::where(['shop_id'=>$this->shop['id'],'content_id'=>$item->hashid,'content_type'=>'course'])->value('marketing_type')?:'';
            $item->membercard_discount = $this->shopHighestDiscount($shopHighestMembercard,$item->join_membercard);
        }
        return $this->output($data);
    }

    /**
     * 课程详情
     * @return mixed
     */
    public function detail($id)
    {
        $this->shopInstance = Shop::where(['hashid'=>$this->shop['id']])->firstOrFail();
        $this->memberInstance = Member::where(['uid' => $this->member['id']])->firstOrFail();
        $data = Course::where(['hashid'=>$id,'shop_id' => $this->shop['id']])
            ->select('hashid','shop_id','title','brief','indexpic','lecturer','subscribe','is_finish','pay_type','price','describe','view_count','course_type','describe','state', 'join_membercard','close_comment')
            ->firstOrFail();
        if($data){
            if ($data->is_lock) {
                return $this->error('course_locked');
            }
            if(intval($data->state) == 0){
                $this->error('off-shelf');
            }
            //学员
            $data->students = $this->students($data);

            //评分
            $data->star = $this->star($data,$this->memberInstance->uid);

            $data->close_comment = boolVal($data->close_comment);
            $data->is_commented = Comment::memberIsCommented($this->memberInstance, 'course', $data->hashid);  
            
            $data->content_id = $data->hashid;
            $data->type = 'course';
            $original_price = $data->price;
            $data->promoter = $this->promoter($this->shop['id'],$this->member['id'],$data->hashid,$data->type,$original_price);
            $data->price = $this->getDiscountPrice($data->price,$data->hashid,$data->type,boolVal($data->join_membercard));
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
            // 拼团信息
            $fg = $this->contentFightGroup($this->shopInstance->id,$this->shopInstance->hashid,'course',$data->hashid);
            $data->fightgroup = $fg ? $fg->id:null;

            $data->lecturer = $data->lecturer ? unserialize($data->lecturer) : [];
            $data->indexpic = $data->indexpic ? hg_unserialize_image_link($data->indexpic ) : '';
            $data->type = 'course';
            $data->is_subscribe = intval($this->checkCourseSubscribe($id));
            $data->subscribe = hg_caculate_multiple($data->subscribe,'subscribe',$this->shop['id']);
            $data->chapter_count = ChapterContent::where(['shop_id'=>$this->shop['id'],'course_id'=>$id])->count();
            $data->class_count = ClassContent::where(['shop_id'=>$this->shop['id'],'course_id'=>$id])->count();
            $data->learn_chapter_count = ClassViews::where(['course_id'=>$id,'member_id'=>$this->member['id']])->select('chapter_id')->distinct()->count();
            $data->learn_class_count = ClassViews::where(['course_id'=>$id,'member_id'=>$this->member['id']])->select('class_id')->distinct()->count();
            $data->remind = ShopContentRemind::where(['shop_id'=>$this->shop['id'],'source'=>$this->member['source'],'content_id'=>$data->hashid,'content_type'=>'course','openid'=>$this->member['openid']])->value('push_status')?:0;
            event(new ContentViewEvent($data,$this->member));
        }
        return $this->output($data);
    }

    private function checkCourseSubscribe($course_id)
    {
        return $this->checkProductPayment('course',$course_id);
    }

    /**
     * 课程付费前详情
     * @param $id
     * @return mixed
     */
    public function freeDetail($id)
    {   
        $member = Member::where('uid',$this->member['id'])->first();
        $this->shopInstance = Shop::where(['hashid'=>$this->shop['id']])->firstOrFail();
        $data = Course::where(['hashid' => $id, 'shop_id' => $this->shop['id']])
            ->select('id', 'hashid', 'shop_id', 'title', 'brief', 'indexpic', 'lecturer', 'subscribe', 'is_finish', 'pay_type', 'price', 'course_type', 'describe', 'state', 'view_count','join_membercard','close_comment')
            ->firstOrFail();
        if($data){
            if($data->state == 0){
                $this->error('off-shelf');
            }

            //学员
            $data->students = $this->students($data);

            //评分
            $data->star = $this->star($data,$member->uid);

            $data->close_comment = boolVal($data->close_comment);
            $data->is_commented = Comment::memberIsCommented($member,'course',$data->hashid);            

            $data->content_id = $data->hashid;
            $data->type = 'course';
            $original_price = $data->price;
            $data->promoter = $this->promoter($this->shop['id'],$this->member['id'],$data->hashid,$data->type,$original_price);
            $data->price = $this->getDiscountPrice($data->price,$data->hashid,$data->type,boolVal($data->join_membercard));
            $price = $data->price;
            if( $original_price != $data->price) {
                $data->cost_price = $original_price;
            }
            $data->market_sign = isset($price['market_sign'])?$price['market_sign']:'';
            if( $limit = $this->limitPurchase($original_price,$data->hashid,'course')){
                $data->market_sign = $limit['market_sign'];
                $data->limit_start = $limit['limit_start'];
                $data->limit_end = $limit['limit_end'];
                $data->limit_state = $limit['limit_state'];
                $data->limit_id = $limit['limit_id'];
                $data->limit_price = $limit['limit_price'];
            }

            // 拼团信息
            $fg = $this->contentFightGroup($this->shopInstance->id,$this->shopInstance->hashid,'course',$data->hashid);
            $data->fightgroup = $fg ? $fg->id:null;

            $data->lecturer = $data->lecturer ? unserialize($data->lecturer) : [];
            $data->indexpic = $data->indexpic ? hg_unserialize_image_link($data->indexpic ) : '';
            $data->type = 'course';
            $data->is_subscribe = intval($this->checkCourseSubscribe($id));
            $data->subscribe =  hg_caculate_multiple($data->subscribe,'subscribe',$this->shop['id']);
            $data->chapter_count = ChapterContent::where(['shop_id'=>$this->shop['id'],'course_id'=>$id])->count();
            $data->class_count = ClassContent::where(['shop_id'=>$this->shop['id'],'course_id'=>$id])->count();
            if($data->is_subscribe == 1) {
                $data->learn_chapter_count = ClassViews::where(['course_id'=>$id,'member_id'=>$this->member['id']])->groupBy('chapter_id')->pluck('id')->count();
                $data->learn_class_count = ClassViews::where(['course_id'=>$id,'member_id'=>$this->member['id']])->groupBy('class_id')->pluck('id')->count();
            }
            $data->remind = ShopContentRemind::where(['shop_id'=>$this->shop['id'],'source'=>$this->member['source'],'content_id'=>$data->hashid,'content_type'=>'course','openid'=>$this->member['openid']])->value('push_status')?:0;
            event(new ContentViewEvent($data,$this->member));
        }
        return $this->output($data);
    }

    /**
     * 章节列表
     * @return mixed
     */
    public function chapterList(){
        $this->validateWithAttribute(['id' => 'required'],['id' => '课程id','type' => '课程类型']);
        $count = request('count') ? : 10;
        $course = Course::where(['shop_id' => $this->shop['id'],'hashid' =>request('id')])->firstOrFail();
        $materialsGroup = $course->materialBindMap();
        $chapter = ChapterContent::where(['shop_id' => $this->shop['id'],'course_id' =>request('id')])
            ->select('id','title','is_top','is_default')
            ->orderBy('order_id')
            ->orderBy('is_top','desc')
            ->orderBy('updated_at','desc')
            ->orderBy('created_at','asc')
            ->paginate($count);
        $data = $this->listToPage($chapter);
        if($chapter){
            foreach ($chapter->items() as $item){
                $chapter_id[] = $item->id;
            }
        }
        if(isset($chapter_id)){
            $select  = ['id','chapter_id','title','is_free','is_top','view_count','content_id','content_type','brief','letter_count'];
            if($course->course_type != 'article'){
                array_push($select, 'content');
            }
            $class = ClassContent::where(['shop_id' => $this->shop['id'],'course_id' =>request('id')])
                ->whereIn('chapter_id',$chapter_id)
                ->select($select)
                ->orderBy('order_id')
                ->orderBy('is_top','desc')
                ->orderBy('updated_at','desc')
                ->orderBy('created_at','asc')
                ->get()->toArray();
            $arr = $filesId = [];
            if($class){
                foreach($class as $item){
                    $item['content'] = isset($item['content']) ? unserialize($item['content']) : [];
                    $ids[] = $item['id'];
                    isset($item['content']['file_id']) && $filesId[] = $item['content']['file_id'];
                }
                $res = ClassViews::selectRaw(\DB::raw('count(id) as sum,class_id'))->whereIn('class_id',$ids)->whereIn('member_id',hg_is_same_member($this->member['id'],$this->shop['id']))->groupBy('class_id')->get()->toArray();
                $info = [];
                $videosInfo = [];
                if($res){
                    foreach($res as $val){
                        $info[$val['class_id']] = $val['sum'];
                    }
                }
                if($course->course_type != 'article' && $course->course_type != 'audio'){
                    $videos = Videos::select('file_id','url','play_set','duration')->where('status',1)->whereIn('file_id',$filesId)->get();
                    if(!$videos->isEmpty()){
                        foreach($videos as $value){
                            $videosInfo[$value['file_id']] = $this->getVideoUrl($value);
                            $duration[$value['file_id']] = $value['duration'];
                        }
                    }
                }
                foreach($class as $item){
                    $item['content'] = isset($item['content']) ? unserialize($item['content']) : [];
                    $item['is_learn'] = isset($info[$item['id']]) ? 1 : 0;
                    $item['view_count'] = hg_caculate_multiple($item['view_count'],'subscribe',$this->shop['id']);
                    if($course->course_type != 'article'){
                        if ($course->course_type == 'audio') {
                            $item['content']['duration'] = isset($item['content']['duration'])?$item['content']['duration']:($item['content']['url'] ? hg_get_file_time($item['content']['url'], 1) : '00:00');
                            ClassContent::where('id',$item['id'])->update(['content'=>serialize($item['content'])]);
                        }else{
                            $item['content']['video_patch'] = isset($videosInfo[$item['content']['file_id']]) ? $videosInfo[$item['content']['file_id']] : '';
                            if(!isset($duration[$item['content']['file_id']]) || !$duration[$item['content']['file_id']]){
                                $item['content']['duration'] = hg_get_file_time($item['content']['video_patch'], 0);
                                Videos::where('file_id',$item['content']['file_id'])->update(['duration'=>$item['content']['duration']]);
                            }
                            $item['content']['duration'] = hg_sec_to_time( isset($duration[$item['content']['file_id']]) ? $duration[$item['content']['file_id']] : 0);
                            $ratio = Videos::where('file_id',$item['content']['file_id'])->value('ratio');
                            $item['content']['ratio'] = $ratio ? unserialize($ratio) : '';
                        }
                    }
                    $content = Content::where(['type'=>$item['content_type'],'hashid'=>$item['content_id']])->first();
                    $item['content_indexpic'] = $content ? hg_unserialize_image_link($content->indexpic) : [];
                    $materialBindKey = Course::materialBindKey($course->hashid, $item['chapter_id'], $item['id']);
                    $item['materials'] = array_key_exists($materialBindKey, $materialsGroup) ? $materialsGroup[$materialBindKey] : [];
                    $arr[$item['chapter_id']][] = $item;
                }
            }
            foreach ($data['data'] as $item_chapter){
                $item_chapter->class_content = isset($arr[$item_chapter->id]) ? $arr[$item_chapter->id] : [];
                $materialBindKey = Course::materialBindKey($course->hashid, $item_chapter->id,null);
                $item_chapter->materials = array_key_exists($materialBindKey, $materialsGroup) ? $materialsGroup[$materialBindKey] : [];
               
            }
        }
        return $this->output($data);
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

    /**
     * 课时浏览量
     * @return mixed
     */
    public function view_count(){
        $this->validateWithAttribute(['id' => 'required|numeric'],['id' => '课时id']);
        $job = (new CourseViewCount($this->shop['id'],request('id'),$this->member))->onQueue(DEFAULT_QUEUE);
        dispatch($job);
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 课程免费订阅(课程免费或课程不免费但有全场免费的会员卡)
     */
    public function subscribeFreeCourse(){
        $this->validateWithAttribute(['course_id'=>'required'],['course_id'=>'课程id']);
        
        $course = Course::where(['hashid'=>request('course_id'),'shop_id'=>$this->shop['id']])->firstOrFail();
        $member = Member::where(['shop_id'=>$this->shop['id'],'uid'=>$this->member['id']])->first();

        $subed = $this->checkCourseSubscribe($course->hashid);
        if($subed){
            return $this->error('already-subscribed');
        }

        $subPermDetail = $this->checkFreeSubscribePermission($member,$course->price, $course->join_membercard);
        
        if(!$subPermDetail['perm']){
            return $this->error('free-subscribe-fail');
        }

        $payment = $this->subscribeCourse($member,$course,$subPermDetail['payment_type'],$subPermDetail['expire_time']);//save payment
        event(new SubscribeEvent(request('course_id'),'course',$this->shop['id'], $this->member['id'], $subPermDetail['payment_type']));//4 免费订阅 5 会员卡免费订阅
        $this->saveCourseId();
        $expireTime = $payment->expire_time ? hg_format_date($payment->expire_time):null;
        return $this->output(['success'=>1,'expire_time'=>$expireTime]);
    }


    private function subscribeCourse($member,$course, $paymentType, $expireTime){
        $payment = Payment::freeSubscribeContent($member, 'course', $course, $paymentType, $expireTime);
        return $payment;
    }

    private function saveCourseId(){
        Redis::sadd('subscribe:applet:'.$this->shop['id'].':'.$this->member['id'],request('course_id'));
        Redis::sadd('subscribe:h5:'.$this->shop['id'].':'.$this->member['id'],request('course_id'));
    }

    /**
     * 用户订阅的课程列表
     */
    public function subscribeCourseList(){
        $data = $this->getSubscribeCourseList();
        $response = $this->getSubscribeCourseListResponse($data);
        return $this->output($this->listToPage($response));
    }

    /**
     * 获取订阅数据
     * @return array
     */
    private function getSubscribeCourseList(){
        $count = request('count') ? : 20;
//        $mobile = Member::where('uid',$this->member['id'])->value('mobile');
//        $member_ids = Redis::smembers('mobileBind:'.$this->shop['id'].':'.$mobile);
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids){
            $content_ids = [];
            foreach ($member_ids as $id){
                if(request('source')=='wx_applet'){
                    $content_id = Redis::smembers('subscribe:applet:'.$this->shop['id'].':'.$id);
                }else{
                    $content_id = Redis::smembers('subscribe:h5:'.$this->shop['id'].':'.$id);
                }
                $content_ids = array_merge($content_ids,$content_id);
            }
        }else {
            if (request('source') == 'wx_applet') {
                $content_ids = Redis::smembers('subscribe:applet:' . $this->shop['id'] . ':' . $this->member['id']);
            } else {
                $content_ids = Redis::smembers('subscribe:h5:' . $this->shop['id'] . ':' . $this->member['id']);
            }
        }
        $subscribe = array_unique($content_ids);
        $data = Course::whereIn('hashid',$subscribe)->where('shop_id',$this->shop['id'])->orderby('update_time','desc')->paginate($count);
        return $data;
    }

    /**
     * 处理列表返回值
     * @param $data
     * @return mixed
     */
    private function getSubscribeCourseListResponse($data){
        if($data['data']){
            foreach ($data['data'] as $item) {
                $item->course_id = $item->hashid;
                $item->create_time = $item->create_time ? date('m-d',$item->create_time) : '';
                $item->update_time = $item->update_time ? date('m-d',$item->update_time) : '';
                $item->indexpic = hg_unserialize_image_link($item->indexpic);
            }
        }
        return $data;
    }


    /**
     * 移动端课时详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function classDetail(){
        $this->validateWithAttribute([
            'course_id' => 'required|alpha_dash|max:64',
            'class_id'  => 'required|numeric'
        ],[
            'course_id' => '课程id',
            'class_id'  => '课时id',
        ]);

        $class = ClassContent::where(['shop_id'=>$this->shop['id'],'course_id'=>request('course_id')])
            ->findOrFail(request('class_id'),['id','shop_id','chapter_id','title','content','content_id','content_type','brief','view_count','is_free']);
        $class->content = $class->content ? unserialize($class->content) : [];
        return $this->output($class);
    }

    public function students($course) {
        $students = $course->students();
        $studentsTotalSql = clone $students;
        $studentsTotal = $studentsTotalSql->count();
        $studentsCollect = $students->orderBy('course_student.created_at','desc')->limit(14)->get();
        return [
            "total"=> $studentsTotal,
            "data"=> $studentsCollect->map(function($item,$key){return ["avatar"=>$item->avatar,"nick_name"=>$item->nick_name];})->toArray()
        ];
    }

    public function star($course, $memberUid) {
        return Comment::star('course',$course->hashid,$memberUid);
    }
}