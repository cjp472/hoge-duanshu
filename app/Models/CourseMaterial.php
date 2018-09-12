<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CourseMaterials;
use App\Models\ChapterContent;
use App\Models\ClassContent;

class CourseMaterial extends Model
{

    protected $table = 'course_material';

    public function baseSerialize() {
        
    }

    static function bindIds($courseId,$chapterId){
        $base = CourseMaterials::where('course_id', $courseId);
        if(is_null($chapterId)){
            $base->whereNotNull('chapter_id')->whereNull('class_id');
            $base->select('chapter_id');
            $r = $base->get()->pluck('chapter_id')->unique()->toArray();
            return $r;
        } else {
            $base->where('chapter_id', $chapterId)->whereNotNull('class_id');
            $r = $base->select('class_id')->get()->pluck('class_id')->unique()->toArray();
            return $r;
        }
        
    }

    static function checkBind($courseId,$chapterId,$classId=null,$materialId=null) {
        $sql = CourseMaterials::where('course_id', $courseId)->where('chapter_id', $chapterId);
        if(is_null($classId)){
            $sql->whereNull('class_id');
        }else{
            $sql->where('class_id', $classId);
        }
        
        if($materialId){
            $sql->where('material_id','!=', $materialId);
        }
        return boolVal($sql->count());
    }

    static function lists($filters,$selectContent=true){
        $list = self::where($filters)
            ->select(
                'course_material.id',
                'course_material.course_id',
                'course_material.title',
                'course_material.created_at',
                'course_material.updated_at',
                'course_material.title',
                'course_material.order',
                'course_material.view_count',
                'course_material.unique_member',
                'chapter_content.id as chapter_id',
                'chapter_content.title as chapter_title',
                'class_content.id as class_id',
                'class_content.is_free as class_is_free',
                'class_content.title as class_title'
            )
            ->join('course_materials', 'course_material.id', '=', 'course_materials.material_id')
            ->leftjoin('chapter_content', 'chapter_content.id', '=', 'course_materials.chapter_id')
            ->leftjoin('class_content', 'class_content.id', '=', 'course_materials.class_id')
            ->orderBy('course_material.order')->orderby('course_material.created_at', 'desc');
        
        if($selectContent){
            $list->addSelect('course_material.content'); 
        }
        return $list;
    }

    static function couresMaterials($courseId) {
        $list = self::where('course_id',$courseId)
            ->orderBy('order')->orderby('created_at', 'desc');
        
        return $list;
    }

    static function deleteMaterials($ids) {
        self::destroy($ids);
        CourseMaterials::whereIn('material_id',$ids)->delete();
    }


    public function detailSerialize() {
        $r = CourseMaterials::where('material_id',$this->id)->firstOrFail();
        $this->bind = null;
        if($r->chapter_id){
            $bind = [];
            $r_c = ChapterContent::where('id', $r->chapter_id)->first();
            $r_c && $bind['chapter'] = ['id' => $r_c->id, 'title' => $r_c->title];
            if ($r->class_id) {
                $r_class = ClassContent::where('id', $r->class_id)->first();
                $bind['class'] = $r_class ? ['id' => $r_class->id, 'title' => $r_class->title]:null;
            }
            $bind && $this->bind = $bind;
        }
    }

    static function listSerialize($materials, $many=true) {
        !$many && $materials = [$materials];
        foreach ($materials as $material) {
            if(is_null($material->chapter_id)) {
                $material->bind = null;
                $material->is_free = 0;
            } else {
                $bind = [
                    'chapter'=>['id'=>$material->chapter_id,'title'=>$material->chapter_title]
                ];
                if(is_null($material->class_id)) {
                    $bind['class'] = null;
                    $material->is_free = 0;
                } else {
                    $bind['class'] = ['id' => $material->class_id, 'title' => $material->class_title];
                    $material->is_free = $material->class_is_free;
                }
                $material->bind = $bind;
            }
        }
    }

}
