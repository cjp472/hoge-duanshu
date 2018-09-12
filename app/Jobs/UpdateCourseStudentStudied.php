<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Models;


class UpdateCourseStudentStudied implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $courseId;
    protected $classId;

    /**
     * Create a new job instance.
     *删除课时时更新学生学习信息
     * @return void
     */
    public function __construct($courseId, $classId)
    {
        $this->courseId = $courseId;
        $this->classId = $classId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $course = Models\Course::where('hashid',$this->courseId)->first();
        if(!$course){
            return;
        }
        $students = Models\CourseStudent::where('course_id',$course->hashid)->get();
        foreach ($students as $student) {
            $this->updateStudentStudied($course,$student);
        }

    }

    public function updateStudentStudied($course,$student){
        $studiedClassCount = Models\CourseStudent::studiedClassCount($student->member_id,$course->hashid);
        $student->studied_class = $studiedClassCount;
        $student->save();
    }
}
