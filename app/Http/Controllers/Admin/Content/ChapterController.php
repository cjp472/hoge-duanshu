<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/9/7
 * Time: 下午4:38
 */
namespace App\Http\Controllers\Admin\Content;
use App\Http\Controllers\Admin\BaseController;
use App\Models\ChapterContent;
use App\Models\ClassContent;
use App\Models\Course;
use App\Models\CourseMaterial;
use Illuminate\Support\Facades\DB;

class ChapterController extends BaseController
{
    /**
     * 章节创建
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createChapter()
    {
        $this->validData();
        $chapterContent = new ChapterContent();
        $chapterNum = $chapterContent->where(['shop_id'=>$this->shop['id'],'course_id'=>request('course_id')])->count();//判断所属内容的章节是否有20个
        $chapterCon = Course::where(['shop_id'=>$this->shop['id'],'hashid'=>request('course_id')])->value('id'); //判断所属课程是否存在
        if ($chapterNum <= 99 && $chapterCon) {
            $chapterContent->shop_id = $this->shop['id'];
            $chapterContent->course_id = request('course_id');
            $chapterContent->title = request('title');
            $chapterContent->created_at = hg_format_date();
            $chapterContent->saveOrFail();
            return $this->output(['success'=>1]);
        }
        $this->error('chapter_create_fail');
    }

    private function validData()
    {
        $this->validateWithAttribute([
            'course_id'  => 'required|alpha_dash',
            'title'      => 'required|string'
        ],[
            'course_id'  => '所属内容id',
            'title'      => '标题'
        ]);
    }

    /**
     * 章节修改
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateChapter()
    {
        $this->validData();
        $this->validateWithAttribute([
            'chapter_id' => 'required|numeric'
        ],[
            'chapter_id' => '课时表id'
        ]);
        ChapterContent::where(['shop_id'=>$this->shop['id'],'course_id'=>request('course_id'),'id'=>request('chapter_id')])
            ->update(['title'=>request('title')]);
        return $this->output(['success'=>1]);
    }

    /**
     * 章节删除
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteChapter()
    {
        $this->validateWithAttribute([
            'course_id'  => 'required|string',
            'chapter_id' => 'required|numeric'
        ],[
            'course_id'  => '课程hashid',
            'chapter_id' => '章节表id'
        ]);
        $classNum = ClassContent::where(['shop_id'=>$this->shop['id'],'course_id'=>request('course_id'),'chapter_id'=>request('chapter_id')])->count();//判断章节下是否还有课时
        if (!$classNum) {
            ChapterContent::destroy(request('chapter_id'));
            return $this->output(['success'=>1]);
        }
        $this->error('chapter_delete_fail');
    }

    /**
     * 章节列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listChapter()
    {
        $this->validateWithAttribute([
            'course_id' => 'required|alpha_dash',
            'title'     => 'alpha_dash',
            'count'     => 'numeric',
            'exclude_bind' => 'numeric'
        ],[
            'course_id' => '所属内容id',
            'title'     => '标题',
            'count'     => '个数',
            'exclude_bind'=>'排除已绑定'
        ]);
        $chapterContent = ChapterContent::select('id','title','is_default');
        request('title') && $chapterContent->where('title','like','%'.request('title').'%');
        if(request('exclude_bind')){
            $exclude_id = CourseMaterial::bindIds(request('course_id'),null);
            $chapterContent->whereNotIn('id', $exclude_id);
        }
         
        $chapterContent = $chapterContent->where(['shop_id'=>$this->shop['id'],'course_id'=>request('course_id')])
            ->orderBy('order_id')
            ->orderBy('is_top','desc')
            ->orderBy('updated_at','desc')
            ->orderBy('created_at','asc');
        $chapters = $chapterContent->get();
        //每个章节的课时数
        $class_num = ClassContent::select(DB::raw('count(id) as class_num'),'chapter_id')
            ->where('course_id',request('course_id'))
            ->groupBy('chapter_id')
            ->pluck('class_num','chapter_id')->toArray();
        if ($chapters) {
            foreach ($chapters as $item) {
                $item->class_num =  array_key_exists($item->id,$class_num) ? $class_num[$item->id] : 0;
            }
        }
        return $this->output($chapters);
    }

    /**
     * 课时详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailChapter()
    {
        $this->validateWithAttribute([
            'chapter_id' => 'required|numeric'
        ],[
            'chapter_id' => '章节表id'
        ]);
        $chapterContent = ChapterContent::where(['shop_id'=>$this->shop['id'],'id'=>request('chapter_id')])->first();
        return $this->output($chapterContent);
    }

    /**
     * 章节置顶操作
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function topChapter()
    {
        $this->validateWithAttribute([
            'chapter_id' => 'required|numeric',
            'is_top'     => 'required|numeric|in:0,1'
        ],[
            'chapter_id' => '章节表id',
            'is_top'     => '是否置顶'
        ]);
        $chapterContent = ChapterContent::find(request('chapter_id'));
        $chapterContent->is_top = request('is_top');
        $chapterContent->updated_at = hg_format_date();
        $chapterContent->save();
        return $this->output(['success'=>1]);
    }

    /**
     * 章节排序
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortChapter(){
        $this->validateWithAttribute([
            'id' => 'required|numeric',
            'order' => 'required|numeric',
            'course_id' => 'required|alpha_dash'
        ], [
            'id' => '章节id',
            'order' => '排序位置',
            'course_id' => '课程id'
        ]);
        
        $chapter = ChapterContent::where(['shop_id' => $this->shop['id'],'course_id'=>request('course_id'),'id'=>request('id')])->firstOrFail();
        $filter = [
            ['shop_id','=',$this->shop['id']],
            ['course_id','=',request('course_id')],
        ];
        $orderBy = [
            ['order_id','asc'],
            ['updated_at','desc'],
            ['created_at','asc']
        ];
        hg_content_sort('chapter_content', 'order_id', $filter, $orderBy, $chapter->id, request('order'));
        return $this->output(['success'=>1]);
    }



}