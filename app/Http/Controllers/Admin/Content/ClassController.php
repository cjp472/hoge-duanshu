<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/9/7
 * Time: 下午2:26
 */
namespace App\Http\Controllers\Admin\Content;
use App\Http\Controllers\Admin\BaseController;
use App\Models\ChapterContent;
use App\Models\ClassContent;
use App\Models\ClassViews;
use App\Models\Content;
use App\Models\Course;
use App\Models\Videos;
use App\Models\CourseMaterial;
use Illuminate\Validation\Rule;
use App\Jobs\UpdateCourseStudentStudied;

class ClassController extends BaseController
{
    /**
     * 新增课时
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createClass()
    {
        $this->validData();
        $chapterContent = ChapterContent::where(['shop_id'=>$this->shop['id'],'course_id'=>request('course_id'),'id'=>request('chapter_id')])->value('id');//所属章节是否存在
        if ($chapterContent) {
            $new_video = [];
            $content = request('content');
            if (request('type') == 'video') {
                $videoContent = Videos::where('file_id',$content['file_id'])->where('status',1)->select('url','cover_url')->first();//video类型内容重写
                if ($videoContent) {
                    $new_video = ['video_patch'=>$videoContent->url,'patch'=>hg_unserialize_image_link($videoContent->cover_url,1)];
                }
            }
            $this->validCreate(array_merge($content,$new_video));
            $this->pushContent($this->shop['id'],request('course_id'),request('title'),'course');
            return $this->output(['success'=>1]);
        }
        $this->error('class_create_fail');

    }

    private function validData()
    {
        $this->validateWithAttribute([
            'course_id'    => 'required|alpha_dash',
            'chapter_id'   => 'required|numeric',
            'title'        => 'required|string|max:128',
            'brief'        => 'string|max:240',
            'content'      => 'required',
            'is_free'      => 'required|numeric|in:0,1',
            'type'         => 'required|string|in:audio,video,article'
        ],[
            'course_id'    => '内容表id',
            'chapter_id'   => '章节表id',
            'title'        => '标题',
            'brief'        => '课时简介',
            'content'      => '内容地址',
            'is_free'      => '是否试看',
            'type'         => '类型'
        ]);
    }

    /**
     * 新增
     */
    private function validCreate($content)
    {
        //产品新需求不需要强制设置试看
//        $free_count = ClassContent::where(['shop_id'=>$this->shop['id'],'course_id'=>request('course_id'),'is_free'=>1])->count();
//        if($free_count==0 && request('is_free')==0){
//            $this->error('least_one_free');
//        }
        $classContent = new ClassContent();
        $classContent->shop_id      = $this->shop['id'];
        $classContent->course_id    = request('course_id');
        $classContent->chapter_id   = request('chapter_id');
        $classContent->title        = request('title');
        $classContent->brief        = trim(request('brief'));
        $classContent->content      = serialize($content);
        $classContent->is_free      = request('is_free');
        $classContent->created_at      = hg_format_date();

        if(request('type') == 'article'){
            $classContent->letter_count = $this->calArticleLetter($content['content']);
        }
        $classContent->saveOrFail();
    }


    public function calArticleLetter($content){
        $readableLetter = preg_replace('/<[^<]+?>/', "", $content);
        $letterCount = mb_strlen($readableLetter);
        return $letterCount;
    }

    /**
     * 修改课时
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClass()
    {
        $this->validData();
        $this->validateWithAttribute([
            'class_id' => 'required|numeric'
        ],[
           'class_id'  => '课时表id'
        ]);
        $chapterContent = ChapterContent::where(['shop_id'=>$this->shop['id'],'id'=>request('chapter_id')])->value('id');
        if ($chapterContent) {
            $new_video = [];
            $content = request('content');
            if (request('type') == 'video') {
                $videoContent = Videos::where('file_id',$content['file_id'])->where('status',1)->select('url','cover_url')->first();
                if ($videoContent) {
                    $new_video = ['video_patch'=>$videoContent->url,'patch'=>hg_unserialize_image_link($videoContent->cover_url,1)];
                }
            }
            $this->validUpdate(array_merge($content,$new_video));
            return $this->output(['success'=>1]);
        }
        $this->error('chapter_not_exist');
    }

    /**
     * 修改
     */
    private function validUpdate($content)
    {
//        $free_count = ClassContent::where(['shop_id'=>$this->shop['id'],'course_id'=>request('course_id'),'is_free'=>1])->get()->toArray();
//        if((count($free_count)==0 && request('is_free')==0) || (count($free_count)==1 && $free_count[0]['id'] == request('class_id') && request('is_free')==0)){
//            $this->error('least_one_free');
//        }
        $classContent = ClassContent::find(request('class_id'));
        $classContent->course_id   = request('course_id');
        $classContent->chapter_id   = request('chapter_id');
        $classContent->title        = request('title');
        $classContent->brief        = trim(request('brief'));
        $classContent->content      = serialize($content);
        $classContent->is_free      = request('is_free');

        if(request('type') == 'article'){
            $classContent->letter_count = $this->calArticleLetter($content['content']);
        }
        
        $classContent->saveOrFail();
    }

    /**
     * 删除课时
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteClass()
    {
        $this->validateWithAttribute([
            'class_id' => 'required|numeric'
        ],[
            'class_id'  => '课时表id'
        ]);
        $class = ClassContent::where('id',request('class_id'))->where('shop_id',$this->shop['id'])->firstOrFail();
        $content = ClassContent::find(request('class_id'),['content_id as hashid','content_type as type']);

        ClassContent::destroy(request('class_id'));
        ClassViews::where('class_id',request('class_id'))->delete();
        //更新内容属于课程字段值
        $content && Content::where($content->toArray())->update(['is_course'=>0]);
        
        $job = (new UpdateCourseStudentStudied($class->course_id,$class->id))->onQueue(DEFAULT_QUEUE);
        dispatch($job);

        return $this->output(['success'=>1]);
    }

    /**
     * 课时列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listClass()
    {
        $this->validateWithAttribute([
            'course_id'  => 'required|alpha_dash',
            'chapter_id' => 'numeric',
            'title'      => 'alpha_dash',
            'is_free'    => 'numeric|in:0,1',
            'count'      => 'numeric'
        ],[
            'course_id'  => '所属内容id',
            'chapter_id' => '所属章节id',
            'title'      => '标题',
            'is_free'    => '是否试看',
            'count'      => '个数'
        ]);
        $classContent = ClassContent::select('id','title','chapter_id','is_free','is_top');
        request('chapter_id') && $classContent->where('chapter_id',request('chapter_id'));
        request('title') && $classContent->where('title','like','%'.request('title').'%');
        array_key_exists('is_free',request()->input()) && $classContent->where('is_free',request('is_free'));
        if (request('exclude_bind')) {
            $exclude_id = CourseMaterial::bindIds(request('course_id'), request('chapter_id'));
            $classContent->whereNotIn('id', $exclude_id);
        }
        $class = $classContent->where(['course_id'=>request('course_id')])
            ->orderBy('order_id')
            ->orderBy('is_top','desc')
            ->orderBy('updated_at','desc')
            ->orderBy('created_at','asc');
        $classes = $class->get();
        if ($classes) {
            foreach ($classes as $item) {
                $item->is_free = intval($item->is_free);
                $item->chapter_id = intval($item->chapter_id);
                $item->is_top = intval($item->is_top);
                $item->chapter_name = $item->belongChapter ? $item->belongChapter->title : '';
            }
        }
        return $this->output($classes);
    }

    /**
     * 课时详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailClass()
    {
        $this->validateWithAttribute([
            'class_id' => 'required|numeric'
        ],[
            'class_id'  => '课时表id'
        ]);
        $classContent = ClassContent::find(request('class_id'));
        if ($classContent) {
            $classContent->content = $classContent->content ? unserialize($classContent->content) : '';
            $classContent->brief = $classContent->brief ? strip_tags($classContent->brief) : '';
            $classContent->chapter_name = $classContent->belongChapter ? $classContent->belongChapter->title : '';
            $classContent->is_free = intval($classContent->is_free);
            $classContent->chapter_id = intval($classContent->chapter_id);
            $classContent->is_top = intval($classContent->is_top);
            return $this->output($classContent);
        }
    }

    /**
     * 课时置顶操作
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function topClass()
    {
        $this->validateWithAttribute([
            'class_id' => 'required|numeric',
            'is_top'   => 'required|numeric|in:0,1'
        ],[
            'class_id'  => '课时表id',
            'is_top'    => '是否置顶'
        ]);
        $classContent = ClassContent::find(request('class_id'));
        $classContent->is_top = request('is_top');
        $classContent->updated_at = hg_format_date();
        $classContent->save();
        return $this->output(['success'=>1]);
    }


    /**
     * 课时排序
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortClass(){
        $this->validateWithAttribute([
            'id' => 'required|numeric',
            'order' => 'required|numeric',
            'course_id' => 'required|alpha_dash|max:64',
        ], [
            'id' => '课时id',
            'order' => '排序位置',
            'course_id' => '课程id'
        ]);
        
        $class = ClassContent::where(['shop_id' => $this->shop['id'],'course_id'=>request('course_id'),'id'=>request('id')])->firstOrFail();
        $filter = [
            ['shop_id','=',$this->shop['id']],
            ['course_id','=',request('course_id')],
            ['chapter_id','=',$class->chapter_id]
        ];
        $orderBy = [
            ['order_id','asc'],
            ['updated_at','desc'],
            ['created_at','asc']
        ];
        hg_content_sort('class_content','order_id',$filter,$orderBy,$class->id,request('order'));
        return $this->output(['success'=>1]);
    }


    /**
     * 课时添加内容，内容列表
     */
    public function classContentList(){
        $this->validateWithAttribute([
            'type'  => 'required|alpha_dash|in:article,audio,video',
        ],[
            'type'  => '内容类型'
        ]);

        $count = request('count') ? : 20;

        $sql = Content::where(['shop_id'=>$this->shop['id'],'type'=>request('type')])
            ->whereNotIn('payment_type',[1,4]);
//            ->where('is_course',0);
        request('title') && $sql->where('title','like','%'.request('title').'%');
        $content = $sql->orderByDesc('update_time')->paginate($count,['hashid as content_id','type','title','indexpic','create_time','up_time','update_time','price']);

        if($content->items()){
            foreach ($content->items() as $item) {
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic ) : [];
                $item->create_time = $item->create_time ? hg_format_date($item->create_time) : '';
                $item->update_time = $item->update_time ? hg_format_date($item->update_time) : '';
                $item->up_time = $item->up_time ? hg_format_date($item->up_time) : '';
            }
        }
        return $this->output($this->listToPage($content));
    }

    /**
     * 添加内容到课程的课时
     */
    public function putContentToClass(){

        $this->validateWithAttribute([
            'course_id'    => 'required|alpha_dash',
            'chapter_id'   => 'numeric',
            'content_type'   => 'required|alpha_dash|in:article,audio,video',
            'content_id'    => 'required|regex:/\w{12}(,\w(12))*$/',
        ],[
            'course_id'    => '课程id',
            'chapter_id'   => '章节id',
            'content_type'  => '内容类型',
            'content_id'    => '内容id',
        ]);


        $course = Course::where(['shop_id'=>$this->shop['id'],'hashid'=>request('course_id')])->value('id');
        if(!$course){
            $this->error('no-course');
        }
        if(request('chapter_id') && !ChapterContent::where(['shop_id'=>$this->shop['id'],'course_id'=>request('course_id')])->find(request('chapter_id'))){
            $this->error('no-chapter');
        }
        $content_ids = explode(',',request('content_id'));
        //如果已经加入到课程了，排除掉

        $content = Content::where(['shop_id'=>$this->shop['id'],'type'=>request('content_type')])
            ->whereIn('hashid',$content_ids)
//            ->where('is_course',0)
            ->get(['hashid','type','title','brief']);
        if($content->isNotEmpty()) {
            $chapter_id = request('chapter_id') ? : ChapterContent::where(['course_id'=>request('course_id'),'is_default'=>1])->value('id');
            if(!$chapter_id){
                //如果默认章节不存在 增加一个默认章节
                $chapter = new ChapterContent;
                $chapter->shop_id = $this->shop['id'];
                $chapter->course_id = request('course_id');
                $chapter->title = '默认章节';
                $chapter->is_default = 1;
                $chapter->save();
                $chapter_id = $chapter->id;
            }
            switch (request('content_type')) {
                case 'article':
                    foreach ($content as $item) {
                        $class_param[] = $this->class_param($item,
                            [
                                'content' => $item->article ? $item->article->content : '',
                            ],
                            $chapter_id
                        );
                    }
                    break;
                case 'audio':
                    foreach ($content as $item) {
                        $class_param[] = $this->class_param(
                            $item,
                            [
                                'file_id'   => 0,
                                'file_name' => $item->audio ? $item->audio->file_name : '',
                                'size'      => $item->audio ? $item->audio->size : 0,
                                'url'       => $item->audio ? $item->audio->url : '',
                            ],
                            $chapter_id
                        );
                    }

                    break;
                case 'video':
                    foreach ($content as $item) {
                        $class_param[] = $this->class_param(
                            $item,
                            [
                                'file_id'     => $item->video ? $item->video->file_id : 0,
                                'file_name'   => $item->video ? $item->video->file_name : '',
                                'size'        => $item->video ? $item->video->size : 0,
                                'patch'       => $item->video ? ($item->video->videoInfo ? hg_unserialize_image_link($item->video->videoInfo->cover_url,1) : '') : '',
                                'video_patch' => $item->video ? ($item->video->videoInfo ? $item->video->videoInfo->url : '') : '' ,
                            ],
                            $chapter_id
                        );
                    }
                    break;
                default :
                    break;
            }
            ClassContent::insert($class_param);
            Content::whereIn('hashid',$content_ids)->where('type',request('content_type'))->update(['is_course'=>1]);
        }
        return $this->output(['success'=>1]);

    }

    /**
     * 课时参数处理
     * @param $item
     * @param $content
     * @param $chapter_id
     * @return array
     */
    private function class_param($item,$content,$chapter_id){
        $return = [
            'shop_id'   => $this->shop['id'],
            'course_id' => request('course_id'),
            'chapter_id'=> $chapter_id,
            'title'     => mb_substr($item->title,0,20,'UTF-8'),
            'brief'     => mb_substr(strip_tags($item->brief),0,40,'UTF-8'),
            'content'   => serialize($content),
            'content_id'    => $item->hashid,
            'content_type'  => $item->type
        ];
        $this->pushContent($this->shop['id'],request('course_id'),mb_substr($item->title,0,20,'UTF-8'),'course');
        return $return;
    }

    public function setFree($courseId,$classId){
        $this->validateWithAttribute(['is_free'=>['required', Rule::in([0,1])]],['is_free'=> '试看设置']);
        $course = $this->getCourse($courseId);
        $class = ClassContent::where('course_id', $course->hashid)->where('id', $classId)->firstOrFail();
        $class->is_free = request('is_free');
        $class->save();
        return $this->output(['success' => 1]);
    }

    private function getCourse($courseId)
    {
        $course = Course::where(['shop_id' => $this->shop['id'], 'hashid' => $courseId])->firstOrFail();
        return $course;
    }

}