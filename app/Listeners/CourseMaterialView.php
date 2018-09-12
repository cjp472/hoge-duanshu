<?php

namespace App\Listeners;

use App\Events\CourseMaterialViewEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models;

class CourseMaterialView
{
    /**
     * Handle the event.
     *
     * @param  CourseMaterialViewEvent  $event
     * @return void
     */
    public function handle(CourseMaterialViewEvent $event)
    {
        $course = $event->course;;
        $material = $event->material;
        $member = Models\Member::where('uid', $event->memberUid)->first();
        if ($course && $material && $member) {
            $this->incrementUvPv($course, $material, $member);
            $this->createViewLog($course, $material, $member);
        }
    }

    public function createViewLog($course, $material, $member)
    {
        $materialView = new Models\CourseMaterialView();
        $materialView->shop_id = $course->shop_id;
        $materialView->course_id = $course->hashid;
        $materialView->material_id = $material->id;
        $materialView->member_id = $member->uid;
        $materialView->source = $member->source;
        $materialView->view_time = time();
        $materialView->save();
    }

    public function incrementUvPv($course, $material, $member)
    {
        $UvCount = Models\CourseMaterialView::where('member_id', $member->uid)
        ->where('material_id', $material->id)->where('course_id', $course->hashid)->count();
      
        !boolVal($UvCount) && Models\CourseMaterial::where('id', $material->id)->increment('unique_member', 1); // UvCount==0 才加一
        Models\CourseMaterial::where('id', $material->id)->increment('view_count', 1);
    }
}
