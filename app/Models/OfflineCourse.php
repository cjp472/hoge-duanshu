<?php
/**

 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OfflineCourse extends Model
{


    protected $table = 'offline_courses_offlinecourse';
    protected $connection = 'djangodb';
    protected $keyType = 'string';
    public $incrementing = false;

}   