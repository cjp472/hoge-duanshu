<?php

namespace App\Events;

class CourseMaterialViewEvent
{

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($course,$material,$memberUid)
    {
        $this->course = $course;
        $this->material = $material;
        $this->memberUid = $memberUid;
    }


}
