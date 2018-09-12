<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models;

class CoursePaiedToStudent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coursepaiedtostudent {shop_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '课程付款会员升级为课程学生';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $shopId = $this->argument('shop_id');
        $shops = [];
        if($shopId){
            $shops = Models\Shop::where('hashid',$shopId)->get();
        } else{
            $shops = Models\Shop::get();
        }

        foreach ($shops as $s) {
            $this->createStudents($s);
        }

    }

    public function createStudents($shop){
        echo "$shop->title $shop->hashid\n";
        $courses = Models\Course::where('shop_id',$shop->hashid)->get();
        foreach ($courses as $c) {
            $this->createCourseStudents($c);
        }
    }

    public function createCourseStudents($course){
        echo "     $course->title  $course->hashid\n";
        $payments = Models\Payment::where('content_id',$course->hashid)->where('content_type','course')->get();
        foreach ($payments as $p) {
            $this->createStudent($course,$p);
        }
        echo "\n\n";
    }

    public function createStudent($course,$payment){
        $member = Models\Member::where('uid',$payment->user_id)->first();
        if(is_null($member)){
            return;
        }
        $isStudent = $course->isCourseStudent($member->uid);
        if (!$isStudent) {
            $membersUid = hg_is_same_member($member->uid, $course->shop_id);
            $sub = Models\ClassViews::where(['course_id' => $course->hashid])
                ->whereIn('member_id', $membersUid)->distinct()->select('course_id', 'chapter_id', 'class_id');
            $studiedClass = DB::table(DB::raw("({$sub->toSql()}) as sub"))
                ->mergeBindings($sub->getQuery())
                ->distinct()
                ->count();

            $viewRecord = Models\ClassViews::where(['course_id' => $course->hashid])
                ->whereIn('member_id', $membersUid)->orderBy('view_time', 'desc')->first();
            $lastStudiedTime = $viewRecord ? date('Y-m-d H:i:s', $viewRecord->view_time) : null;


            $student = new Models\CourseStudent;
            $student->member_id = $member->uid;
            $student->course_id = $course->hashid;
            $student->entrance = array_key_exists($payment->payment_type, Models\CourseStudent::PAYMENT_TYPE_TO_ENTRANCE) ? Models\CourseStudent::PAYMENT_TYPE_TO_ENTRANCE[$payment->payment_type] : 'unkonw';
            $student->studied_class = $studiedClass;
            $student->last_studied_time = $lastStudiedTime;
            $student->save();

            echo "          create $member->nick_name  $member->uid  learn $studiedClass  entrance $student->entrance  l_time $lastStudiedTime \n";
        }else{
            echo "          ignore  $member->nick_name  $member->uid\n";
        }
    }
}
