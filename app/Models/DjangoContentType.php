<?php
/**

 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DjangoContentType extends Model
{


    protected $table = 'django_content_type';
    protected $connection = 'djangodb';

    const OfflineCourseContentType = ['app_label' =>'offline_courses','model'=>'offlinecourse'];


    static function getOfflineCourseContentType() {
      return parent::where(self::OfflineCourseContentType)->first()->id;
    }


}