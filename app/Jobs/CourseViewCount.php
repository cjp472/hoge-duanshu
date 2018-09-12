<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2017/6/23
 * Time: 上午10:13
 */


namespace App\Jobs;


use App\Models\ClassContent;
use App\Models\ClassViews;
use App\Models\Course;
use App\Models\CoursePreViewer;

use App\Events\ErrorHandle;


use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class CourseViewCount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $shop_id;
    protected $class_id;
    protected $member;
    /**
     * CourseViewCount constructor.
     * @param $shop_id
     * @param $class_id
     */
    public function __construct($shop_id,$class_id,$member)
    {
        $this->shop_id = $shop_id;
        $this->class_id = $class_id;
        $this->member = $member;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        //记录浏览课时的用户信息
        $class = ClassContent::find($this->class_id);
        if (!$class) {
            return;
        }
        
        $course = Course::where('hashid', $class->course_id)->first();
        if (!$course) {
            return;
        }


        DB::beginTransaction();
        try {
            $this->viewLog($class);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            event(new ErrorHandle($e));
            return;
        }

        $this->studentLearnLog($course, $class);
        $class->is_free && $this->preViewerLearnLog($course);

    }


    public function viewLog($class){
        $class_views_param = [
                'shop_id' => $this->shop_id,
                'course_id' => $class->course_id,
                'chapter_id'    => $class->chapter_id,
                'class_id'      => $this->class_id,
                'view_time'     => time(),
                'member_id'     => $this->member['id'],
                'source'        => $this->member['source'],
                'user_agent'    => request()->server->get('HTTP_USER_AGENT'),
                'ip'            => request()->ip()
            ];
        $class_views = new ClassViews();
        $class_views->setRawAttributes($class_views_param);
        $class_views->save();

        ClassContent::where(['id'=>$this->class_id,'shop_id'=>$this->shop_id])->increment('view_count', 1);
    }

    public function studentLearnLog($course,$class) {
        $membersUid = hg_is_same_member($this->member['id'], $this->shop_id);
        $IsStudent = $course->isCourseStudent($this->member['id']);
        if ($IsStudent) {
            $base = DB::table('course_student')->where('course_id', $course->hashid)->whereIn('member_id', $membersUid);
            $baseA = clone $base;
            $baseA->update(['last_studied_time'=>date('Y-m-d H:i:s')]);
            $classLearnedCount = ClassViews::where(['course_id'=> $course->hashid, 'class_id'=> $this->class_id])
                ->whereIn('member_id', $membersUid)->count();
            $isNewClass = $classLearnedCount == 1 ? true:false;
            if ($isNewClass) {
                $baseB = clone $base;
                $baseB->increment('studied_class', 1);
            }
        }
    }

    public function preViewerLearnLog($course){
        $now = date('Y-m-d H:i:s');
        $base = CoursePreViewer::where(['course_id'=>$course->hashid,'member_id'=>$this->member['id']]);
        $baseA = clone $base;
        $preViewer = $baseA->first();
        $IsStudent = $course->isCourseStudent($this->member['id']);

        if($IsStudent) {
            return;
        }
        if($preViewer){
            $baseB = clone $base;
            $baseB->increment('pre_view_num', 1,['last_studied_time'=>$now]);
        }else
        {
            $newPreViewer = new CoursePreViewer();
            $newPreViewer->course_id = $course->hashid;
            $newPreViewer->pre_view_num = 1;
            $newPreViewer->last_studied_time = $now;
            $newPreViewer->member_id = $this->member['id'];
            $newPreViewer->save();
        }
    }
}
