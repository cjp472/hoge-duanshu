<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Course extends Model
{
    protected $connection = 'mysql';

    protected $table = 'course';

    public $timestamps = false;

    protected $fillable = ['sales_total', 'unit_member', 'promoter_rate', 'invite_rate', 'is_participate_promotion'];

    public $hidden = ['class_hour'];

    public function class_hour()
    {   //关联课时表
        return $this->hasMany('App\Models\ClassContent', 'course_id', 'hashid');
    }
    static public function getCourseByIds($courseIds)
    {
        return static::whereIn('hashid', $courseIds)->get();
    }

    /**
     * @param array $options
     * @return bool|void
     */
    public function create(array $options = [])
    {
        try {
            $this->hashid = uuid4();
            self::save($options);
        } catch (QueryException $e) {
            $errorInfo = $e->errorInfo;
            if ($errorInfo[1] == 1062 && strstr($errorInfo[2], 'course_hashid_unique')) {
                $this->create($options);
            }
        }
    }

    public function students()
    {
        $that = $this;
        $now = time();
        $select = [
            'course_student.*',
            'member.nick_name', 'member.true_name', 'member.sex', 'member.avatar', 'member.mobile', 'member.birthday', 'member.source',
            DB::raw("coalesce(hg_order.price,'0.0') as tuition"),
            DB::raw("case when hg_payment.expire_time = 0  then 1 when hg_payment.expire_time is NULL  then 1 when hg_payment.expire_time > $now then 1 else 0 end as status")
        ];
        return CourseStudent::where('course_id', $this->hashid)
            ->join('member', 'member.uid', '=', 'course_student.member_id')
            ->leftjoin('order', function($join) use($that) {
                $join->where('course_student.entrance','buy')
                    ->where('order.content_type','=','course')
                    ->where('order.content_id','=',$that->hashid)
                    ->where('order.pay_status', '=',1)
                    ->where('order.order_type', '!=',2)
                    ->on('order.user_id','=','member.uid');
            }) // 当entrance是购买时才查payment
            ->leftjoin('payment', function($join) use($that) {
                $join->where('course_student.entrance','member_card_sub')
                    ->where('payment.content_type','=','course')
                    ->where('payment.content_id','=',$that->hashid)
                    ->on('payment.user_id','=','member.uid');
                })
            ->where('member.shop_id',$this->shop_id)
            ->select($select);
    }

    public function profile()
    {
        $totalChapter = ChapterContent::where('course_id', $this->hashid)->count();
        $totalClass = ClassContent::where('course_id', $this->hashid)->count();
        return ['total_chapter' => $totalChapter, 'total_class' => $totalClass];
    }

    public function studyProfile()
    {
        $class_learn_pv = ClassViews::where('course_id', $this->hashid)->count();
        $student_num = CourseStudent::where('course_id', $this->hashid)->count();
        $previewer_num = CoursePreViewer::where('course_id',$this->hashid)->count();
        return [
            'view_count' => $this->view_count,
            'study_num' => $class_learn_pv,
            'student_num' => $student_num,
            'previewer_num' => $previewer_num
        ];
    }

    public function isCourseStudent($member_uid)
    {
        $membersUid = hg_is_same_member($member_uid, $this->shop_id);
        $num = CourseStudent::whereIn('member_id', $membersUid)->where('course_id', $this->hashid)->count();
        return boolVal($num);
    }

    public function struct($search_title = null, $is_free = null, $include_study_profile = false, $remove_empty = false)
    {   
        $that = $this;
        $serializeClass = function ($classes)use($that) {
            foreach ($classes as $item) {
                try{
                    switch ($that->course_type) {
                        case 'article':
                            $item->attrs = $item->letter_count.'字';
                            break;
                        case 'video':
                            $item->attrs = '';
                            $c = unserialize($item->content);
                            // array_key_exists('duration', $c) && $item->attrs .= '时长：'.$c['duration'].' ';
                            array_key_exists('size',$c) && $item->attrs .= '大小：'.$c['size'].'M';
                            break;
                        case 'audio':
                            $item->attrs = '';
                            $c = unserialize($item->content);
                            array_key_exists('duration', $c) && $item->attrs .= '时长：'.$c['duration'];
                            // array_key_exists('size', $c) && $item->attrs .= '大小：'.$c['size'].'M';
                            break;
                        default:
                            $item->attrs = '';
                            break;
                    }
                }catch(\Exception $e){
                    $item->attrs = '';
                }
                $item->makeHidden(['shop_id', 'course_id', 'content', 'is_top', 'brief', 'order_id', 'content_id', 'content_type','letter_count']);

            }
            $c = new Collection($classes);
            return $c->toArray();
        };

        $serializeMaterial = function ($materials) {
            foreach ($materials as $item) {
                $item->makeHidden(['content', 'order']);
            }
            $c = new Collection($materials);
            return $c->toArray();
        };

        $serializeChapter = function ($chapters) use ($remove_empty) {
            $_chapters = [];
            foreach ($chapters as $item) {
                if (count($item->classes) == 0 && $remove_empty) {
                    continue;
                } else {
                    $item->makeHidden([]);
                    $_chapters[] = $item;
                }
            }
            $c = new Collection($_chapters);
            return $c->toArray();
        };

        $getGroupKey = function ($courseId, $chapterId, $classId) {
            return $courseId . ':' . $chapterId . ':' . $classId;
        };


        $struct = [];
        // chapter
        $chapters = ChapterContent::list($this->hashid);
        $chapters = $chapters->get();
        $chaptersId = $chapters->pluck('id')->toArray();
        // class
        $chaptersclasses = ClassContent::chaptersClasses($chaptersId, $search_title, $is_free);
        // material
        $materials = CourseMaterial::lists(['course_material.course_id' => $this->hashid])->get();
        $materialsGroup = $materials->groupBy(function ($item, $key) use ($getGroupKey) {
            return $getGroupKey($item->course_id, $item->chapter_id, $item->class_id);
        });

        //
        if ($include_study_profile) {

            $viewsPv = ClassViews::where('course_id', $this->hashid)
                ->groupby('course_id', 'class_id')
                ->select('course_id', 'chapter_id', 'class_id', DB::raw('count(*) as pv'))->get();

            $sub = ClassViews::where('course_id', $this->hashid)
                ->select('course_id', 'class_id', 'member_id')->distinct();
            $viewsUv = DB::table(DB::raw("({$sub->toSql()}) as sub"))
                ->mergeBindings($sub->getQuery())
                ->groupby('course_id', 'class_id')
                ->select('*', DB::raw('count(*) as uv'))
                ->get();

            $viewsPvGroup = $viewsPv->groupBy(function ($item, $key) {
                return $item->course_id . ':' . $item->class_id;
            });

            $viewsUvGroup = $viewsUv->groupBy(function ($item, $key) {
                return $item->course_id . ':' . $item->class_id;
            });

        }

        $key = $getGroupKey($this->hashid, null, null);
        $keyExist = $materialsGroup->has($key);
        $struct['materials'] = $keyExist ? $materialsGroup[$key] : [];
        $struct['chapters'] = [];
        foreach ($chapters as $chapter) {
            $chapter->classes = $chaptersclasses[$chapter->id];
            foreach ($chapter->classes as $class) {
                $key = $getGroupKey($this->hashid, $chapter->id, $class->id);
                $keyExist = $materialsGroup->has($key);
                $class->materials = $keyExist ? $materialsGroup[$key] : [];
                $class->materials = $serializeMaterial($class->materials);

                $viewKey = $this->hashid . ':' . $class->id;
                $include_study_profile && $class->pv = $viewsPvGroup->has($viewKey) ? $viewsPvGroup[$viewKey]->first()->pv : 0;
                $include_study_profile && $class->uv = $viewsUvGroup->has($viewKey) ? $viewsUvGroup[$viewKey]->first()->uv : 0;

            }

            $chapter->classes = $serializeClass($chapter->classes);
            $key = $getGroupKey($this->hashid, $chapter->id, null);
            $keyExist = $materialsGroup->has($key);
            $chapter->materials = $keyExist ? $materialsGroup[$key] : [];
            $chapter->materials = $serializeMaterial($chapter->materials);
            $chapter->class_count = count($chapter->classes);
            $struct['chapters'][] = $chapter;

        }
        $struct['chapters'] = $serializeChapter($struct['chapters']);
        $struct['materials'] = $serializeMaterial($struct['materials']);
        // dd($struct);
        // dd($materialsGroup);
        // dd($chapters, $chaptersId, $chaptersclasses, $materials);
        return $struct;
    }

    public function preViewers($source = null, $nick_name = null, $sub = null)
    {        
        $sql = DB::table('course_pre_viewer')->join('member', 'member.uid', '=', 'course_pre_viewer.member_id')
                ->leftjoin('course_student', function ($join) {
                    $join->on('course_student.member_id', '=', 'course_pre_viewer.member_id')->on('course_student.course_id', '=', 'course_pre_viewer.course_id');
                })
                ->select(
                    'course_pre_viewer.member_id',
                    'course_pre_viewer.pre_view_num',
                    'course_pre_viewer.last_studied_time as last_pre_view_time', // 不改文档了
                    'member.avatar',
                    'member.nick_name',
                    'member.source',
                    DB::raw('case when hg_course_student.member_id is not null then 1 else 0 end as subscribed')
                )
                ->where('member.shop_id',$this->shop_id)
                ->where('course_pre_viewer.course_id', $this->hashid)
                ->orderBy('course_pre_viewer.created_at','desc')
                ;

        if ($nick_name) {
            $sql->where('member.nick_name', 'LIKE', '%' . $nick_name . '%');
        }
        if ($source) {
            $sql->where('member.source', $source);
        }
        if (!is_null($sub)) {
            if ($sub) {
                $sql->whereNotNull('course_student.member_id');
            } else {
                $sql->whereNull('course_student.member_id');
            }

        }
        return $sql;
    }


    static function materialBindKey($courseId, $chapterId, $classId)
    {
        return $courseId . ':' . $chapterId . ':' . $classId;
    }

    public function materialBindMap()
    {
        // $_self = Course;
        $materials = CourseMaterial::lists(['course_material.course_id' => $this->hashid], false)->get();
        $materialsGroup = $materials->groupBy(function ($item, $key) {
            return self::materialBindKey($item->course_id, $item->chapter_id, $item->class_id);
        });
        return $materialsGroup->toArray();
    }

}
