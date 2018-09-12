<?php
namespace App\Listeners;

use App\Events\SubscribeEvent;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\MemberCard;
use App\Models\CourseStudent;
use App\Models\Payment;
use App\Models\ClassViews;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class AddSubscribers implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * 队列名称
     * @var string
     */
    public $queue = DEFAULT_QUEUE;

    /**
     * Handle the event.
     *
     * @param  SubscribeEvent  $event
     * @return void
     */
    public function handle(SubscribeEvent $event)
    {
        $count = Payment::where('content_id',$event->id)
            ->where('shop_id',$event->shop_id)
            ->where('content_type',$event->type)
            ->count();
        switch($event->type){
            case 'column':
                Column::where([
                    'hashid'    => $event->id,
                    'shop_id'   => $event->shop_id,
                ])->update(['subscribe'=>$count]);
                
                Content::where([
                    'hashid'    => $event->id,
                    'shop_id'   => $event->shop_id,
                    'type'   => 'column',
                ])->update(['subscribe'=>$count]);
                break;
            case 'course':
                $course = Course::where([
                    'hashid' => $event->id,
                    'shop_id' => $event->shop_id,
                ])->first();
                Course::where([
                    'hashid'    => $event->id,
                    'shop_id'   => $event->shop_id,
                ])->update(['subscribe'=>$count]);
                Content::where([
                    'hashid'    => $event->id,
                    'shop_id'   => $event->shop_id,
                    'type'   => 'course',
                ])->update(['subscribe'=>$count]);
                $this->addCourseStudent($course,$event->member_id, $event->payment_type);
                break;
            case 'member_card':
                MemberCard::where([
                    'hashid'    => $event->id,
                    'shop_id'   => $event->shop_id,
                ])->update(['subscribe'=>$count]);
                break;
            default:
                Content::where([
                    'hashid'    => $event->id,
                    'shop_id'   => $event->shop_id,
                    'type' => $event->type,
                ])->update(['subscribe'=>$count]);
            break;
        }

    }

    private function addCourseStudent($course,$memberUid, $payment_type) {
        $entrance = array_key_exists($payment_type, CourseStudent::PAYMENT_TYPE_TO_ENTRANCE) ?
            CourseStudent::PAYMENT_TYPE_TO_ENTRANCE[$payment_type]:'unkonw';
        if(!$course->isCourseStudent($memberUid)){
            $membersUid = hg_is_same_member($memberUid, $course->shop_id);
            
            $sub = ClassViews::where(['course_id' => $course->hashid])
                ->whereIn('member_id', $membersUid)->select('class_id')->distinct();
            $studiedClass = DB::table(DB::raw("({$sub->toSql()}) as sub"))
                ->mergeBindings($sub->getQuery())
                ->count();


            $viewRecord = ClassViews::where(['course_id' => $course->hashid])
                ->whereIn('member_id', $membersUid)->orderBy('view_time','desc')->first();
            $lastStudiedTime = $viewRecord ? date('Y-m-d H:i:s',$viewRecord->view_time) : null;
            

            $student = new CourseStudent;
            $student->member_id = $memberUid;
            $student->course_id = $course->hashid;
            $student->entrance = $entrance;
            $student->studied_class = $studiedClass;
            $student->last_studied_time = $lastStudiedTime;
            $student->save();
        } else{
            CourseStudent::where('member_id',$memberUid)->where('course_id',$course->hashid)->update(['entrance'=>$entrance]);
        }
    }

    public function failed(SubscribeEvent $event)
    {
        file_put_contents(storage_path('/logs/faileQueue.txt'),date('Y.m.d H:i:s').'subscribe-content-id:'.$event->id."\n",FILE_APPEND);
    }
}
