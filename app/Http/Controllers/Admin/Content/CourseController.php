<?php

namespace App\Http\Controllers\Admin\Content;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Events\Content\CreateEvent;
use App\Events\Content\EditEvent;
use App\Events\AppEvent\AppContentDeleteEvent;

use App\Models\ChapterContent;
use App\Models\ClassContent;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Course;
use App\Models\Comment;
use App\Models\Views;
use App\Models\MarketingActivity;
use App\Models\PromotionContent;
use App\Models\PromotionShop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Vinkla\Hashids\Facades\Hashids;


class CourseController extends ContentController
{
    /**
     * 列表
     */
    public function lists()
    {
        $count = request('count') ? : 10;
        $course = Course::where('shop_id',$this->shop['id']);
        $price = request('price');
        $priceAtNoActivity = request('price_at_no_activity');

        if(isset($price)){
            $course->where('price','>',$price);
            $pur_ids = hg_check_marketing($this->shop['id'],'course');
            $pur_ids && $course->whereNotIn('hashid',$pur_ids);
        }
        
        request('title') && $course->where('title','like','%'.request('title').'%');
        request('state') != null && $course->where('state',request('state'));
        request('is_finish') != null && $course->where('is_finish',request('is_finish'));
        isset($priceAtNoActivity) && $course->where('price', '>', $priceAtNoActivity);
        
        $result = $course->select('hashid','shop_id','title','indexpic','price','state','is_finish','create_time','course_type','top','subscribe', 'join_membercard',
            'view_count','unique_member','sales_total')
            ->orderBy('order_id')
            ->orderBy('top','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->paginate($count);
        $data = $this->listToPage($result);

        // uv 统计
        $coursesId = [];
        foreach ($data['data'] as $item) {
            $coursesId[] = $item->hashid;
        }
        $coursesUv = $this->coursesUv($coursesId);

        foreach ($data['data'] as $item){
            $item->create_time = hg_format_date($item->create_time);
            $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : '';
            $item->is_finish = intval($item->is_finish);
            $item->state = intval($item->state);
            $item->subscribe = intval($item->subscribe);
            $item->stage = $item->class_hour ? $item->class_hour->count() : 0;
            $item->type = 'course';
            $item->uv = $coursesUv->has($item->hashid)? $coursesUv[$item->hashid]->first()->uv:0;
        }
        return $this->output($data);
    }

    /*


     */
    public function coursesUv($coursesId){
        $sub = Views::whereIn('content_id', $coursesId)->where('content_type','course')->select('content_id','member_id')->distinct();
        $coursesUv = DB::table(DB::raw("({$sub->toSql()}) as sub"))
            ->mergeBindings($sub->getQuery())
            ->groupBy('content_id')
            ->select('content_id',DB::raw('count(*) as uv'))
            ->get()
            ;
        $coursesUvGroup = $coursesUv->groupBy('content_id');
        return $coursesUvGroup;
    }

    /**
     * 编辑详情接口
     */
    public function detail($id)
    {
        $data = Course::where(['hashid'=>$id,'shop_id'=>$this->shop['id']])
            ->select('hashid','shop_id','title','indexpic','pay_type','price','state','brief','lecturer','describe','is_finish','create_time','course_type','subscribe', 'join_membercard', 'close_comment', 'sales_total','view_count', 'unique_member')
            ->firstOrFail();
        $type_id = ContentType::where('content_id',$id)->pluck('type_id')->toArray();
        
        $data->lecturer = $data->lecturer ?  unserialize($data->lecturer) : [];
        $data->create_time = hg_format_date($data->create_time);
        $data->indexpic = hg_unserialize_image_link($data->indexpic);
        $data->state = intval($data->state);
        $data->pay_type = intval($data->pay_type);
        $data->is_finish = intval($data->is_finish);
        $data->type = 'course';
        $data->type_id = $type_id;
        $data->market_activities = content_market_activities($this->shop['id'],'course', $data->hashid);
        $data->star = $this->star($data);
        
        $contentProfile = $data->profile();
        $data->hour_count = $contentProfile['total_class']; // 兼容旧数据
        $data->total_chapter = $contentProfile['total_chapter'];
        $data->total_class = $contentProfile['total_class'];

        $studyProfile = $data->studyProfile();
        $data->view_count = $studyProfile['view_count'];
        $data->study_num = $studyProfile['study_num'];
        $data->student_num = $studyProfile['student_num'];
        $data->previewer_num = $studyProfile['previewer_num'];
        return $this->output($data);
    }

    public function star($course)
    {
        return Comment::star('course', $course->hashid);
    }

    /**
     * 新增
     */
    public function createCourse()
    {
        $this->validateCourse();
        $course = $this->formatCourse();
        $result = $this->createCourseData($course);  //插入course表和默认章节
        $data = ['content_id'=>$result->hashid,'type_id'=>request('type_id'),'type'=>'course'];
        $this->createOrUpdateType($data);
        return $this->output($result);
    }


    /**
     * 删除
     *
     * @return void
     */
    public function delete(){
        $shopId = $this->shop['id'];

        $this->validateWithAttribute(['id'=>'required|array|max:50|min:1','id.*'=>'alpha_dash|max:64'], ['id'=>'课程id']);

        $inputIdCollection = new Collection(request('id'));

        $validCourse = Course::where(['shop_id'=>$shopId])->whereIn('hashid',request('id'))->select('hashid','title','id')->get()->unique('hashid');
        $validCourseMap = $validCourse->groupBy('hashid');
        // $validColumnId = $validColumn->pluck('id')->toArray();
        $validCourseHashId = $validCourse->pluck('hashid')->toArray();
        
        $invalidId = $inputIdCollection->diff($validCourseHashId);
        if($invalidId->count()){
            return $this->errorWithText('invalid-course-id', '无效id '.join($invalidId->toArray(),'、'));
        }


        $classCount = ClassContent::where(['shop_id'=>$shopId])->whereIn('course_id', $validCourseHashId)
            ->groupBy('course_id')->select('course_id', DB::raw('count(*) as count'))->get();
        
        $notEmptyClass = $classCount->filter(function($value,$key){
            return $value->count > 0;
        });

        $count = $notEmptyClass->count();
        if ($count) {
            $titles = [];
            foreach ($notEmptyClass as $value) {
                $first = $validCourseMap[$value->course_id]->first();
                $titles[]= $first->title;
            }
            $errMsg = '当前课程'.join($titles, '、').'目录下有正在上架的课时，请删除后再操作';
            return $this->errorWithText('not-empty-course-class', $errMsg);
        }

        $ChapterCount = ChapterContent::where(['shop_id'=>$shopId,'is_default'=>0])->whereIn('course_id',$validCourseHashId)
            ->groupBy('course_id')->select('course_id',DB::raw('count(*) as count'))->get();
        $notEmptyChapter = $ChapterCount->filter(function($value,$key){
            return $value->count > 0;
        });

        $count = $notEmptyChapter->count();
        if ($count) {
            $titles = [];
            foreach ($notEmptyChapter as $value) {
                $first = $validCourseMap[$value->course_id]->first();
                $titles[]= $first->title;
            }
            $errMsg = '当前课程'.join($titles, '、').'目录下有正在上架的章节，请删除后再操作';
            return $this->errorWithText('not-empty-course-chapter', $errMsg);
        }

        ChapterContent::whereIn('course_id',$validCourseHashId)->where('shop_id',$shopId)->delete();
        Course::whereIn('hashid', $validCourseHashId)->where('shop_id',$shopId)->delete();
        Content::whereIn('hashid',$validCourseHashId)->where('type','course')->delete();
        
        PromotionContent::where('content_type', 'course')->whereIn('content_id', $validCourseHashId)->delete();

        foreach ($validCourseHashId as $i) {
            $data = ['content_id'=>$i,'shop_id'=>$shopId,'type'=>'course'];
            event(new AppContentDeleteEvent($data));
        }

        return $this->output(['success'=>1]);
    }

    /**
     * 更新
     */
    public function updateCourse()
    {
        $this->validateUpdateCourse();
        $course = $this->formatCourse(1);
        Cache::forget('content:'.$this->shop['id'].':'.'course'.':'.request('id'));
        $update_course = Course::where(['shop_id' => $this->shop['id'],'hashid'=>request('id')])->firstOrFail();
        $update_course->setRawAttributes($course);
        $update_course->saveOrFail();
        $update_course->hashid = request('id');
        event(new EditEvent($update_course->hashid,'course',$this->setContentCourse($update_course),$this->shop['id'],$this->user));
        $update_course->create_time = hg_format_date($update_course->create_time);
        $update_course->indexpic = hg_unserialize_image_link($update_course->indexpic);
        $data = ['content_id'=>request('id'),'type_id'=>request('type_id'),'type'=>'course'];
        $this->createOrUpdateType($data);
        $update_course->type_id = request('type_id');
        return $this->output($update_course);
    }

    /**
     * 课程上下架
     * @return mixed
     */
    public function shelf()
    {
        $this->validateWithAttribute(['id'=>'required','state'=>'required'],['id'=>'内容id','state'=>'上下架状态']);
        $param = explode(',',request('id'));
        Course::where('shop_id',$this->shop['id'])->whereIn('hashid',$param)->update(['state'=>request('state')]);

        $order_id = Course::where(['shop_id' => $this->shop['id']])
            ->orderBy('order_id')
            ->orderBy('top','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->pluck('hashid');

        foreach ($param as $item) {
            event(new EditEvent($item,'course',[
                'state' => request('state') ? 1 : 2, //内容上下架状态值和课程不同
                'up_time'   => time(),
            ],$this->shop['id'],$this->user));

            //上架时排序
            if(request('state') == 1) {
                //排到第一位
                $old_order = Course::where(['hashid' => $item])->firstOrFail() ? Course::where(['hashid' => $item])->firstOrFail()->order_id : (isset(array_flip($order_id->toArray())[$item]) ? array_flip($order_id->toArray())[$item] + 1 : 0);
                hg_sort($order_id, $item, 0, $old_order, 'course');
            }
        }
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|void
     * 课程置顶
     */
    public function top(){
        $this->validateWithAttribute(['id'=>'required','top'=>'required'],['id'=>'课程id','top'=>'置顶状态']);
        $course = Course::where(['shop_id'=>$this->shop['id'],'hashid'=>request('id')])->first();
        if($course){
            $course->top = request('top');
            $course->update_time = time();
            $course->saveOrFail();
            return $this->output(['success'=>1]);
        }
        return $this->error('no_content');
    }

    /**
     * 课程完结
     * @return mixed
     */
    public function finish(){
        $this->validateWithAttribute(['id'=>'required','is_finish'=>'required'],['id'=>'专栏id','is_finish'=>'完结状态']);
        $param = explode(',',request('id'));
        Course::where('shop_id',$this->shop['id'])->whereIn('hashid',$param)->update(['is_finish'=>request('is_finish')]);
        foreach ($param as $item) {
            event(new EditEvent($item,'course',[],$this->shop['id'],$this->user));
        }
        return $this->output(['success'=>1]);
    }


    private function validateCourse(){
        $this->validateWithAttribute(
            [
                'title'        => 'required|string|max:150',
                'brief'        => 'required|max:250',
                'course_type'  => 'required|in:audio,video,article',
                'indexpic'     =>'required',
                'pay_type' =>'required',
                'describe'     =>'max:100000'
            ],[
                'title'        => '课程名称',
                'brief'        => '课程简介',
                'course_type'  => '课程类型',
                'indexpic'     =>'课程图片',
                'pay_type' =>'付费类型',
                'describe'     => '描述',
        ]);
    }

    private function validateUpdateCourse(){
        $this->validateWithAttribute(
            [
                'id'           => 'required|alpha_dash',
                'title'        => 'required|string|max:150',
                'brief'        => 'required|max:250',
                'course_type'  => 'required|in:audio,video,article',
                'indexpic'     =>'required',
                'pay_type' =>'required',
                'describe'     =>'max:100000'
            ],[
                'id'           => '课程id',
                'title'        => '课程名称',
                'brief'        => '课程简介',
                'course_type'  => '课程类型',
                'indexpic'     =>'课程图片',
                'pay_type' =>'付费类型',
                'describe'     => '描述',
        ]);
    }

    private function formatCourse($sign=''){
        $data = [
            'title'            => request('title'),
            'shop_id'          => $this->shop['id'],
            'brief'            => request('brief'),
            'indexpic'         => hg_explore_image_link(request('indexpic')),
            'course_type'      => request('course_type'),
            'pay_type'         => request('pay_type'),
            'lecturer'         => request('lecturer') ? serialize(request('lecturer')) : '',
            'describe'         => request('describe'),
        ];
        if(!$sign){
            $data['create_time'] = time();
            $data['state'] = 1;
            $data['create_user'] = $this->user['id'];
        }
        if(request('shelf')){
            $data['state'] = 1;
        }
        if(request('pay_type') == 1){
            $this->validateWithAttribute(['price' => 'required'],['price' => '价格']);
            $data['price'] = request('price');
        }else{
            $data['price'] = 0;
        }
        if($sign && request('course_type')){//不能编辑类型
            unset($data['course_type']);
        }
        if($data['price'] > MAX_ORDER_PRICE){
            $this->error('max-price-error');
        }
        // 加入到营销活动的商品，可以编辑基本信息，不能编辑价格
        if($sign) {
            $c = Course::where(['hashid'=>request('id'),'shop_id'=>$this->shop['id']])->firstOrFail();
            $is_join_market_activity = content_is_join_any_market_activity($this->shop['id'],'course',$c->hashid,MarketingActivity::COMMON_ACTIVITY);
            if($is_join_market_activity && request('price') && ($data['price'] != $c->price)) {
                $this->error('update-marketing-activity-content');
            }
            if(is_null(request('price'))){
                unset($data['price']);
            }
        }
        return $data;
    }

    private function createCourseData($course){
        $data = new Course();
        $data->setRawAttributes($course);
        $data->create();
        $hashid = $data->hashid;
        event(new CreateEvent(
            $hashid, $this->setContentCourse($data),'course',$this->shop['id'],$this->user
        ));
        ChapterContent::insert(['shop_id' => $this->shop['id'],'course_id' => $hashid,'title' => '默认章节','is_default' => 1]);
        $this->createPromotionContent($hashid, 'course');
        $data->create_time = hg_format_date($data->create_time);
        $data->indexpic = hg_unserialize_image_link($data->indexpic);
        return $data;
    }

    private function setContentCourse($info)
    {
        return [
            'title'  => $info->title,
            'brief' => strip_tags($info->brief),
            'indexpic'  => $info->indexpic,
            'state' => intval($info->state),
            'payment_type'  => $info->pay_type ? 2 : 3 ,
            'price'  => $info->price,
        ];
    }

    /**
     * 课程排序
     */
    public function sort(){
        $this->validateWithAttribute([
            'id' => 'required|alpha_dash',
            'order' => 'required|numeric'
        ], [
            'id' => '课程id',
            'order' => '排序位置'
        ]);
        
        $course = Course::where(['shop_id' => $this->shop['id'],'hashid'=>request('id')])->firstOrFail();

        $filter = [
            ['shop_id','=',$this->shop['id']],
        ];
        $orderBy = [
            ['order_id','asc'],
            ['update_time','desc'],
            ['create_time','desc']
        ];

        hg_content_sort('course', 'order_id', $filter, $orderBy, $course->id, request('order'));
        
        return $this->output(['success'=>1]);
    }

    public function close_comment($courseId){
        $this->validateWithAttribute([
            'close' => ['required', Rule::in([true,false,0,1])]
        ], [
            'close' => '评论开关',
        ]);
        $course = Course::where(['shop_id' => $this->shop['id'], 'hashid' => $courseId])->firstOrFail();
        $course->close_comment = request('close');
        $course->save();
        return $this->output(['success' => 1]);
    }
}