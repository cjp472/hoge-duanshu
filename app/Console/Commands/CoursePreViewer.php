<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models;

class CoursePreViewer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coursepreviewer {shop_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '课程试学学生';

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
        if ($shopId) {
            $shops = Models\Shop::where('hashid', $shopId)->get();
        } else {
            $shops = Models\Shop::get();
        }

        foreach ($shops as $s) {
            $this->createStudents($s);
        }
    }

    public function createStudents($shop)
    {
        echo "$shop->title $shop->hashid\n";
        $courses = Models\Course::where('shop_id', $shop->hashid)->get();
        foreach ($courses as $c) {
            $this->createCourseStudents($c);
        }
    }

    public function createCourseStudents($course)
    {
        echo "     $course->title  $course->hashid\n";
        $viewersId = Models\ClassViews::join('class_content', 'class_views.class_id', '=', 'class_content.id')
                ->where('class_content.course_id', $course->hashid)
                ->where('class_content.is_free',1)
                ->where('class_content.shop_id',$course->shop_id)
                ->select('member_id')
                ->distinct()
                ->get()
                ->pluck('member_id')
                ->toArray();
        foreach ($viewersId as $v) {
            $this->createStudent($course, $v);
        }
        echo "\n\n";
    }

    public function createStudent($course, $v)
    {   
        $member = Models\Member::where('uid', $v)->first();
        $existed = boolVal(Models\CoursePreViewer::where('member_id',$v)
                ->where('course_id',$course->hashid)
                ->count());
        if($member && !$existed){

            $preViewNum = Models\ClassViews::join('class_content', 'class_views.class_id', '=', 'class_content.id')
                ->where('class_content.course_id',$course->hashid)
                ->where('class_views.member_id',$v)
                ->where('class_content.is_free',1)
                ->where('class_content.shop_id',$course->shop_id)
                ->count();
            
            $lastView = Models\ClassViews::where('course_id',$course->hashid)
                ->where('shop_id',$member->shop_id)
                ->where('member_id',$v)->orderBy('view_time','desc')->first();

            $preStudent = new Models\CoursePreViewer();
            $preStudent->course_id = $course->hashid;
            $preStudent->member_id = $member->uid;
            $preStudent->pre_view_num = $preViewNum;
            $preStudent->last_studied_time = $lastView ? date('Y-m-d H:i:s',$lastView->view_time) : null;
            $preStudent->save();
            echo "          create $member->nick_name  $member->uid  pre_view_num $preViewNum l_time $preStudent->last_studied_time \n";
        }else {
            echo "          ignore  $v\n";
        }
        
    }
}
