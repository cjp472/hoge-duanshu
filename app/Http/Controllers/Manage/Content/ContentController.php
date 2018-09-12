<?php
namespace App\Http\Controllers\Manage\Content;

use App\Http\Controllers\Manage\BaseController;
use App\Models\AliveMessage;
use App\Models\Manage\Chapter;
use App\Models\Manage\ClassContent;
use App\Models\Manage\Column;
use App\Models\Manage\Content;
use App\Models\Manage\Course;
use App\Models\Manage\Payment;
use App\Models\Manage\Shop;
use App\Models\Manage\Views;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ContentController extends BaseController
{

    /**
     *根据内容hashid查询内容详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail()
    {
        $this->validateWith([
            'content_id'  => 'required|alpha_dash',
            'type'        => 'required|alpha_dash|in:article,video,audio,live,course',
            'title'       => 'alpha_dash'
        ]);
        if (request('type') == 'course') {
            $detail = [];
            $course = Course::where('hashid',request('content_id'))->select('title','shop_id','create_user','course_type')->first();
            $chapter = Chapter::where('course_id',request('content_id'))->orderBy('is_top','desc');
            request('title') && $chapter->where('title','like','%'.request('title').'%');
            $chapter = $chapter->get();
            if ($course && !$chapter->isEmpty()) {
                foreach ($chapter as $item) {
                    $item->is_top = intval($item->is_top);
                    $item->is_default = intval($item->is_default);
                }
                $detail['content_id'] = request('content_id');
                $detail['title'] = $course->title;
                $detail['shop_id'] = $course->shop_id;
                $detail['shop_title'] = $course->belongShop ? $course->belongShop->title : '';
                $detail['create_user'] = $course->create_user ? $course->create_user : 0;
                $detail['user_name'] = $course->belongUser ? $course->belongUser->name : '';
                $detail['chapter'] = $chapter;
                $detail['type'] = $course->course_type;
            }

        } else {
            $sql = Content::where('hashid',request('content_id'));
            request('type') == 'article' && $sql->join('article','article.content_id','=','content.hashid')->select('content.*','article.*');
            request('type') == 'video' && $sql->join('video','video.content_id','=','content.hashid')->select('content.*','video.*');
            request('type') == 'audio'&& $sql->join('audio','audio.content_id','=','content.hashid')->select('content.*','audio.*');
            request('type') == 'live' && $sql->join('live','live.content_id','=','content.hashid')->select('content.hashid as content_id','content.*','live.*');
            $detail = $sql->first();
            $this->deatiFormat($detail);
        }
        return $this->output($detail);
    }

    /**
     * 章节详情(单个章节下的所有课时)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailChapter()
    {
        $this->validateWith([
            'course_id'   => 'required|alpha_dash',
            'chapter_id'  => 'numeric',
            'title'       => 'alpha_dash',
            'is_free'     => 'numeric'
        ]);
        $detail = [];
        $course = Course::where('hashid',request('course_id'))->select('title','shop_id','create_user')->first();
        $class = ClassContent::where('course_id',request('course_id'))
            ->orderBy('is_top','desc')
            ->orderBy('updated_at','desc')
            ->orderBy('created_at','asc');
        request('chapter_id') && $class->where('chapter_id',request('chapter_id'));
        request('title') && $class->where('title','like','%'.request('title').'%');
        request('is_free') && $class->where('is_free',request('is_free'));
        $class = $class->get();
        if (!$class->isEmpty()) {
            foreach ($class as $item) {
                $item->content = $item->content ? unserialize($item->content) : '';
                $item->chapter_title = $item->belongChapter ?  $item->belongChapter->title : '';
            }
        }
        if ($course && !$class->isEmpty()) {
            $detail['content_id'] = request('course_id');
            $detail['title'] = $course->title;
            $detail['shop_id'] = $course->shop_id;
            $detail['shop_title'] = $course->belongShop ? $course->belongShop->title : '';
            $detail['create_user'] = $course->create_user ? $course->create_user: 0;
            $detail['user_name'] = $course->belongUser ? $course->belongUser->name : '';
            $detail['class'] = $class;
        }
        return $this->output($detail);
    }

    /**
     * 课时详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailClass()
    {
        $this->validateWith([
            'course_id'   => 'required|alpha_dash',
            'chapter_id'  => 'required|numeric',
            'class_id'    => 'required|numeric'
        ]);
        $classContent = ClassContent::where(['course_id'=>request('course_id'),'chapter_id'=>request('chapter_id'),'id'=>request('class_id')])->first();
        if ($classContent) {
            $classContent->content = unserialize($classContent->content);
        }
        return $this->output($classContent);
    }


    /**
     * 详情数据整理
     *
     * @param $detail
     */
    protected function deatiFormat($detail)
    {
        if ($detail) {
            $detail->up_time = $detail->up_time ? date('Y-m-d H:i:s',$detail->up_time) : '';
            $detail->create_time = $detail->create_time ? hg_format_date($detail->create_time) : '' ;
            $detail->indexpic = $detail->indexpic ? hg_unserialize_image_link($detail->indexpic) : '';
            $detail->column_title = $detail->belongsToColumn ? $detail->belongsToColumn->title : '';
            $detail->user_name = $detail->belongsToUsers ? $detail->belongsToUsers->name : '';
            $detail->shop_title = $detail->belongsToShop ? $detail->belongsToShop->title : '';
//            $view = Views::select(DB::raw('count(distinct member_id) as view,content_id'))->groupBy('content_id')->pluck('view','content_id')->toArray();
//            $buy = Payment::select(DB::raw('count(user_id) as buys,content_id'))->groupBy('content_id')->pluck('buys','content_id')->toArray();
//            array_key_exists($detail->content_id,$view) ? $detail->views = intval($view[$detail->content_id]) : $detail->views = 0;
//            array_key_exists($detail->content_id,$buy) ? $detail->buy = intval($buy[$detail->content_id]) : $detail->buy = 0;
            $detail->is_lock = $detail->is_lock == 1 ? true : false;
            switch (request('type')) {
                case 'video':
                    $detail->url = $detail->video ? $detail->video->videos->url : '';
                    break;
                case 'live':
                    $detail->live_person = $detail->live_person ? json_decode($detail->live_person, true) : [];
                    ($detail->start_time > time()) && $detail->live_state = 0;
                    ($detail->start_time < time()) && ($detail->end_time > time()) && $detail->live_state = 1;
                    ($detail->end_time < time()) && $detail->live_state = 2;
                    $detail->start_time = $detail->start_time ? hg_format_date($detail->start_time) : '';
                    $detail->end_time = $detail->end_time ? hg_format_date($detail->end_time) : '';
                    $detail->live_indexpic = $detail->live_indexpic ? hg_unserialize_image_link($detail->live_indexpic) : '';
                    $detail->url = ($detail->live && $detail->live->videos) ? $detail->live->videos->url : '';
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * 专栏列表
     * @return mixed
     */
    public function columnList()
    {
        $this->validateWithAttribute([
            'count'   => 'numeric',
            'title'   => 'alpha_dash',
            'finish'  => 'numeric',
            'charge'  => 'numeric',
            'state'     => 'numeric',
            'start_time'    => 'date',
            'end_time'      => 'date',
        ],[
            'count'   => '个数',
            'title'   => '标题',
            'finish'  => '是否完结',
            'charge'  => '是否收费',
            'state'     => '上下架状态'
        ]);
        $count = request('count') ? : 20;
        $column = Column::select('hashid','shop_id','title','indexpic','brief','describe','price','state','create_time','update_time','finish','top','charge','subscribe');
        request('title') && $column->where('title','like','%'.request('title').'%');
        array_key_exists('finish',request()->input()) && $column->where('finish',request('finish'));
        array_key_exists('charge',request()->input()) && $column->where('charge',request('charge'));
        array_key_exists('state',request()->input()) && $column->where('state',request('state'));
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $column = $column->whereBetween('update_time',[$start_time,$end_time])->orderBy('create_time','desc')->paginate($count);
        if ($column->items()) {
            foreach ($column->items() as $item) {
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : [];
                $item->create_time = $item->create_time ? hg_format_date($item->create_time) : '';
                $item->update_time = $item->update_time ? hg_format_date($item->update_time) : '';
            }
        }
        return $this->output($this->listToPage($column));
    }

    /**
     * 专栏详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function columnDetail()
    {
        $this->validateWithAttribute([
            'id'   => 'required|alpha_dash'
        ],[
            'id' => '专栏hashid'
        ]);
        $detail = Column::where('hashid',request('id'))->firstOrFail();
        if($detail){
            $detail->shop_title = Shop::where('hashid',$detail->shop_id)->value('title');
            $detail->create_time = $detail->create_time ? hg_format_date($detail->create_time) : '';
            $detail->update_time = $detail->update_time ? hg_format_date($detail->update_time) : '';
            $detail->charge = intval($detail->charge);
            $detail->stage = count($detail->content);
            $detail->indexpic = hg_unserialize_image_link($detail->indexpic);
        }
        return $this->output($detail);
    }

    /**
     *
     * 专栏下的内容列表
     * @return \Illuminate\Http\JsonResponse
     *
     *
     */
    public function getContentByColumnId()
    {
        $this->validateWith([
            'column_id'   =>   'required|alpha_dash',
            'count'       =>   'numeric'
        ]);
        $count = request('count') ? : 20;
        $column_id = Column::where('hashid',request('column_id'))->value('id') ? : $this->error('no-column-info');
        $res = Content::where('column_id',$column_id)->select('hashid as content_id','title','indexpic','shop_id','payment_type','up_time','price','type','create_time','comment_count','view_count','subscribe','play_count','end_play_count','share_count')
            ->whereNotIn('type',['column','course'])
            ->orderBy('create_time','desc')
            ->paginate($count);
        if ($res->items()) {
            foreach ($res->items() as $item) {
                $item->up_time = $item->up_time ? hg_format_date($item->up_time) : '';
                $item->create_time = $item->create_time ? hg_format_date($item->create_time) : '' ;
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : '';
            }
        }
        return $this->output($this->listToPage($res));
    }


    /**
     * 今日增长统计
     * @return array
     */
    public function analysisAddContent(){
        $start_time = strtotime(date('Y-m-d 00:00:00',time()));

        $todayContent = array_merge($this->fourContent($start_time),$this->columnContent($start_time));//今日增长
        $allContent = array_merge($this->allFourContent(),$this->allColumn());//总增长
        $keyArray = ['article','audio','video','live','column'];
        foreach ($keyArray as $item){
            isset($todayContent[$item])? : $todayContent[$item] = 0;
            isset($allContent[$item])? : $allContent[$item] = 0;
        }
        return $this->output(['todayIncrease' => $todayContent,'allIncrease' => $allContent]);

    }

    /**
     * 今日图文等增长
     * @param $start_time
     * @return mixed
     */
    private function fourContent($start_time){
        $result = Content::whereBetween('create_time',[$start_time,time()])
            ->groupBy('type')
            ->select(DB::raw('count(id) as todayCount'),'type')->pluck('todayCount','type')->toArray();
        return $result;
    }

    /**
     * 今日专栏增长
     * @param $start_time
     * @return array
     */
    private function columnContent($start_time){
        $count = Column::whereBetween('create_time',[$start_time,time()])->count();
        $result =['column' => $count];
        return $result;
    }

    /**
     * 总图文等增长
     * @return mixed
     */
    private function allFourContent(){
        $result = Content::groupBy('type')
            ->select(DB::raw('count(id) as todayCount'),'type')->pluck('todayCount','type')->toArray();
        return $result;
    }

    /**
     * 总专栏增长
     * @return array
     */
    private function allColumn(){
        $count = Column::count();
        $result =['column' => $count];
        return $result;
    }

    /**
     * 增长分析
     * @return mixed
     */
    public function increaseAnalysis(){
        $this->validateWith([
            'time' => 'numeric|in:0,1,2'
        ]);
        $typeData = $this->timeType();
        return $this->output($typeData);
    }

    private function timeType(){
        switch (request('time')){
            case '1' :
                $result = $this->getDateType();
                break;
            case '2' :
                $result = $this->getMonthType();
                break;
            default :
                $result = $this->getHourType();
                break;
        }
        return $result;
    }

    /**
     * 按天
     */
    private function getDateType(){
        return $this->getDayMonthDetail('m/d',date("m/d", mktime(0, 0, 0, date("m"), 1, date("Y"))));
    }

    /**
     * 按月
     */
    private function getMonthType(){
        return $this->getDayMonthDetail('m',date('01'));
    }

    /**
     * 按时
     */
    private function getHourType(){
        $start_time = strtotime(date('Y-m-d 00:00:00',time()));
        $articleInfo = $this->increaseArticle('',$start_time,time(),date('00:00'));
        $audioInfo = $this->increaseAudio('',$start_time,time(),date('00:00'));
        $videoInfo = $this->increaseVideo('',$start_time,time(),date('00:00'));
        $liveInfo = $this->increaseLive('',$start_time,time(),date('00:00'));
        $columnInfo = $this->increaseColumn('',$start_time,time(),date('00:00'));
        return $result = [
            'article'  => $articleInfo,
            'audio'    => $audioInfo,
            'video'    => $videoInfo,
            'live'     => $liveInfo,
            'column'   => $columnInfo
        ];

    }

    /**
     * 天，月类型的
     * @param $type
     * @return array
     */
    private function getDayMonthDetail($type,$begin){
        $articleInfo = $this->increaseArticle($type,0,time(),$begin);
        $audioInfo = $this->increaseAudio($type,0,time(),$begin);
        $videoInfo = $this->increaseVideo($type,0,time(),$begin);
        $liveInfo = $this->increaseLive($type,0,time(),$begin);
        $columnInfo = $this->increaseColumn($type,0,time(),$begin);
        return $result = [
            'article'  => $articleInfo,
            'audio'    => $audioInfo,
            'video'    => $videoInfo,
            'live'     => $liveInfo,
            'column'   => $columnInfo
        ];
    }

    private function increaseArticle($type,$start,$end,$begin){
        $res = Content::where('type','article')
            ->whereBetween('create_time',[$start,$end])
            ->select(DB::raw('1 as counts'),'create_time')->pluck('counts','create_time');
        if(request('time') == 1 || request('time') == 2){
            return $this->getDataTypeIncrease($res,$type,$begin,date($type));
        }else{
            return $this->getTypeIncrease($res);
        }
    }
    private function increaseAudio($type,$start,$end,$begin){
        $res = Content::where('type','audio')
            ->whereBetween('create_time',[$start,$end])
            ->select(DB::raw('1 as counts'),'create_time')->pluck('counts','create_time');
        if(request('time') == 1 || request('time') == 2){
            return $this->getDataTypeIncrease($res,$type,$begin,date($type));
        }else{
            return $this->getTypeIncrease($res);
        }
    }
    private function increaseVideo($type,$start,$end,$begin){
        $res = Content::where('type','video')
            ->whereBetween('create_time',[$start,$end])
            ->select(DB::raw('1 as counts'),'create_time')->pluck('counts','create_time');
        if(request('time') == 1 || request('time') == 2){
            return $this->getDataTypeIncrease($res,$type,$begin,date($type));
        }else{
            return $this->getTypeIncrease($res);
        }
    }
    private function increaseLive($type,$start,$end,$begin){
        $res = Content::where('type','live')
            ->whereBetween('create_time',[$start,$end])
            ->select(DB::raw('1 as counts'),'create_time')->pluck('counts','create_time');
        if(request('time') == 1 || request('time') == 2){
            return $this->getDataTypeIncrease($res,$type,$begin,date($type));
        }else{
            return $this->getTypeIncrease($res);
        }
    }
    private function increaseColumn($type,$start,$end,$begin){
        $res = Column::whereBetween('create_time',[$start,$end])
            ->select(DB::raw('1 as counts'),'create_time')->pluck('counts','create_time');
        if(request('time') == 1 || request('time') == 2){
            return $this->getDataTypeIncrease($res,$type,$begin,date($type));
        }else{
            return $this->getTypeIncrease($res);
        }
    }

    /**
     * 内容类型-计算数量
     * @param $info
     * @param $type
     * @return array
     */
    private function getDataTypeIncrease($info,$type,$start,$end){
        $list = $back = $keys = $values = [];
        if($info){
            foreach ($info as $key=>$item) {
                $hour = date($type, $key);
                isset($back[$hour]) ? $back[$hour]++ :  $back[$hour] = 0 ;
            }
            for($i = $start;$i <= $end;$i++)
            {
                $date = str_pad($i,2,0,STR_PAD_LEFT);
                $keys[] = $date;
                $values[] = isset($back[$date]) ? $back[$date] : 0;
            }

            $list = ['keys'=>$keys,'values'=>$values];

        }
        return $list;
    }
    /**
     * 按时计算-返回数据
     * @param $info
     * @return array
     */
    private function getTypeIncrease($info){
        if($info){
            $back = [];
            foreach ($info as $key=>$item) {
                $hour = intval(date('H', $key));
                isset($back[$hour]) ? $back[$hour] += $item : $back[$hour]=$item;
            }
            $keys = $values = [];
            for($i = 0;$i <= date('H');$i++)
            {
                $keys[] = str_pad($i,2,0,STR_PAD_LEFT).':00';
                $values[] = isset($back[$i]) ? intval($back[$i]) : 0;
            }
            return array('keys'=>$keys,'values'=>$values);
        }
    }





    /****************2017-6-21*******************/



    /**
     * 内容列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function listContent()
    {
        $this->validateWith([
            'type'         => 'required|alpha_dash|in:article,video,audio,live,course',
            'count'        => 'numeric',
            'shop_id'      => 'alpha_dash',
            'title'        => 'string',
            'state'        => 'numeric',
            'order'        => 'alpha_dash|in:price,update_time,up_time,buy,view',
            'order_method' => 'alpha_dash|in:desc,asc',
            'payment_type' => 'numeric|in:1,2,3,4',
            'start_time'   => 'date',
            'end_time'     => 'date',
            'pay_type'     => 'numeric|in:0,1'
        ]);
        $count = request('count') ? :50;
        if (request('type') == 'course') {
            $list = $this->courseContent($count);
        } else {
            $list = $this->otherContent($count);
        }
        return $this->output($this->listToPage($list));
    }

    /**
     * 商店下内容
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopContent()
    {
        $this->validateWith([
            'shop_id'    => 'required|alpha_dash',
            'type'       => 'alpha_dash|in:course,article,audio,video,live,column',
            'count'      => 'numeric',
            'page'       => 'numeric'
        ]);
        $count = request('count') ? : 15;

        if (!request('type')) {
            $content = Content::select('hashid as content_id','shop_id','title','indexpic','price','payment_type as pay_type','update_time','create_time','is_lock','type');
        } else {
            if (request('type') == 'course') {
                $content = Course::select('hashid as content_id','shop_id','title','indexpic','price','pay_type','update_time','create_time','is_lock');
            } elseif (request('type') == 'column') {
                $content = Column::select('hashid as content_id','shop_id','title','indexpic','price','charge as pay_type','update_time','create_time','display as is_lock');
            } else {
                $content = Content::select('hashid as content_id','shop_id','title','indexpic','price','payment_type as pay_type','update_time','create_time','is_lock')->where('type',request('type'));
            }
        }
        $content = $content->where('shop_id',request('shop_id'))->orderBy('update_time','desc')->paginate($count);
        if ($content->items()) {
            foreach ($content->items() as $item) {
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : '';
                $item->update_time = $item->update_time ? hg_format_date($item->update_time) : '';
                $item->create_time = $item->create_time ? hg_format_date($item->create_time) : '';
                $item->views = $item->view_count;
                $item->buy = $item->subscribe;
                $item->is_lock = $item->is_lock == 1 ? true : false ;
                $item->shop_black = $item->belongsToShop ? $item->belongsToShop->is_black : 0;
                $item->shop_black = $item->shop_black == 1 ? true : false ;
                $item->type = request('type') ? request('type') : $item->type;
            }
        }
        return $this->output($this->listToPage($content));
    }

    /**
     * 课时内容
     *
     * @param $view
     * @param $buy
     * @param $count
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function courseContent($count)
    {
        $course = Course::select('hashid as content_id','shop_id','title','indexpic','state','is_finish','pay_type','create_time','price','update_time','is_lock','course_type');
        request('shop_id') && $course->where('shop_id',request('shop_id'));
        request('title') && $course->where('title','like','%'.request('title').'%');
        array_key_exists('pay_type',request()->input()) && $course->where('pay_type',request('pay_type'));
        $start_time = strtotime(request('start_time'));
        $end_time = strtotime(request('end_time'));
        $start_time && !$end_time && $course->whereBetween('create_time',[$start_time,time()]);
        $end_time && !$start_time && $course->whereBetween('create_time',[0,$end_time]);
        $start_time && $end_time && $course->whereBetween('create_time',[$start_time,$end_time]);
        array_key_exists('state',request()->input()) && $course->where('state',request('state'));
        $order = request('order') ? : 'update_time';
        $order_method = request('order_method') ? : 'desc';
        $list = $course->orderBy($order,$order_method)->paginate($count);
        if ($list->items()) {
            foreach ($list->items() as $item) {
                $item->state = intval($item->state);
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : '';
                $item->update_time = $item->update_time ? hg_format_date($item->update_time) : '';
                $item->create_time = $item->create_time ? hg_format_date($item->create_time) : '';
                $item->views = $item->view_count;
                $item->buy = $item->subscribe;
                $item->shop_black = $item->belongShop ? $item->belongShop->is_black : 0;
                $item->is_lock = $item->is_lock == 1 ? true : false ;
                $item->shop_black = $item->shop_black == 1 ? true : false ;
                $item->is_finish = $item->is_finish == 1 ? true : false ;
                $item->type = 'course';
            }
        }
        return $list;
    }

    /**
     * 其他四种内容
     *
     * @param $view
     * @param $buy
     * @param $count
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function otherContent($count)
    {
        $content = Content::select('hashid as content_id','title','indexpic','shop_id','payment_type','column_id','up_time','state','price','display','is_lock','type','create_time','create_user','update_time');
        request('shop_id') && $content->where('shop_id',request('shop_id'));
        request('type') && (request('type') != 'live') && (request('type') != 'audio') && $content->where('type', request('type'));
        request('type') && (request('type') == 'live') && $content = Content::select('content.hashid as content_id','content.title','content.indexpic','content.shop_id','content.payment_type','content.column_id','content.up_time','content.state','content.price','content.display','content.is_lock','content.type','content.create_time','content.create_user','content.update_time','live.*')
            ->where('content.type',request('type'))
            ->join('live','live.content_id','=','content.hashid');
        request('type') && (request('type') == 'audio') && $content = Content::select('content.hashid as content_id','content.title','content.indexpic','content.shop_id','content.payment_type','content.column_id','content.up_time','content.state','content.price','content.display','content.is_lock','content.type','content.create_time','content.create_user','content.update_time','audio.*')
            ->where('content.type',request('type'))
            ->join('audio','audio.content_id','=','content.hashid');
        request('title') && $content->where('title','like','%'.request('title').'%');
        request('payment_type') && $content->where('payment_type',request('payment_type'));
        $start_time = strtotime(request('start_time'));
        $end_time = strtotime(request('end_time'));
        $start_time && !$end_time && $content->whereBetween('create_time',[$start_time,time()]);
        $end_time && !$start_time && $content->whereBetween('create_time',[0,$end_time]);
        $start_time && $end_time && $content->whereBetween('create_time',[$start_time,$end_time]);
        $state = request('state');
        if (array_key_exists('state',request()->input())) {
            switch ($state) {
                case 0:
                    $content->where('up_time','>',time())->where('state','!=',2);
                    break;
                case 1:
                    $content->where('up_time','<',time())->where('state','!=',2);
                    break;
                case 2:
                    $content->where('state',2);
                    break;
            }
        }
        $order = request('order') ? : 'update_time';
        $order_method = request('order_method') ? : 'desc';
        $list = $content->orderBy($order,$order_method)->paginate($count);
        $this->listFormat($list);
        return $list;
    }


    /**
     * 格式化列表数据
     * @param $list
     * @param $view
     */
    protected function listFormat($list)
    {
        if ($list->items()) {
            foreach ($list->items() as $item) {
                $item->up_time = $item->up_time ? hg_format_date($item->up_time) : '';
                $item->create_time = $item->create_time ? hg_format_date($item->create_time) : '' ;
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : '';
                $item->shop_black = $item->belongsToShop ? $item->belongsToShop->is_black : 0;
                $item->views =  intval($item->view_count);
                $item->buy = intval($item->subscribe);
                $item->is_lock = $item->is_lock == 1 ? true : false ;
                $item->shop_black = $item->shop_black == 1 ? true : false ;
                ($item->up_time > time()) && ($item->state !=2) && $item->state = 0;
                ($item->up_time < time()) && ($item->state !=2) && $item->state = 1;
                ($item->state == 2) && $item->state = intval($item->state);
                $item->update_time = $item->update_time ? hg_format_date($item->update_time) : '';
                if (request('type') == 'live') {
                    $item->live_person = $item->live_person ? json_decode($item->live_person, true) : [];
                    ($item->start_time > time()) && $item->live_state = 0;
                    ($item->start_time < time()) && ($item->end_time > time()) && $item->live_state = 1;
                    ($item->end_time < time()) && $item->live_state = 2;
                    $item->start_time = $item->start_time ? hg_format_date($item->start_time) : '';
                    $item->end_time = $item->end_time ? hg_format_date($item->end_time) : '';
                    $item->live_indexpic = $item->live_indexpic ? hg_unserialize_image_link($item->live_indexpic) : '';
                    $item->url = ($item->live && $item->live->videos) ? $item->live->videos->url : '';
                } elseif (request('type') == 'video') {
                    $item->url = ($item->video && $item->video->videos) ? $item->video->videos->url : '';
                    $item->size = $item->video ? $item->video->size : 0;
                    $item->file_name = $item->video ? $item->video->file_name : 0;
                }
            }
        }
    }


    /**
     * 内容上下架
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState()
    {
        $this->validateWith([
            'id'     => 'required|alpha_dash',
            'state'  => 'required|numeric',
            'type'  => 'alpha_dash'
        ]);

        switch(request('type')){
            case 'column' :
                $column = Column::where('hashid',request('id'))->firstOrFail();
                $column->state  = request('state');
                $column->save();
                break;
            case 'course':
                $course = Course::where('hashid',request('id'))->firstOrFail();
                $course->state  = request('state');
                $course->save();
                break;
            default:
                Content::where('hashid',request('id'))->update(['state'=>request('state'),'update_time'=>time()]);
                break;
        }
        return $this->output(['success'=>1]);
    }


    /**
     * 内容锁定
     * @return \Illuminate\Http\JsonResponse
     */
    public function contentLock()
    {
        $this->validateWith([
            'content_id' => 'required|alpha_dash',
            'lock'      => 'required|numeric|in:0,1',
            'type'      => 'alpha_dash',
        ]);
        switch (request('type')){
            case 'course':
                $course = Course::where('hashid',request('content_id'))->first();
                $course->is_lock = request('lock');
                $course->save();
                break;
            case 'column':
                $column = Column::where('hashid',request('content_id'))->first();
                $column->is_lock = request('lock');
                $column->save();
                break;
            default:
                $state = request('lock') == 1 ? 2 : 0;
                Content::where('hashid',request('content_id'))->update(['is_lock'=>request('lock'),'state'=>$state,'update_time'=>time()]);
                break;
        }

        return $this->output(['success'=>1]);
    }

    /**
     * 内容显示隐藏
     * @return \Illuminate\Http\JsonResponse
     */
    public function contentDisplay()
    {
        $this->validateWith([
            'content_id' => 'required|alpha_dash',
            'display'    => 'required|numeric|in:0,1'
        ]);
        $content = Content::where('hashid',request('content_id'))->first();
        if ($content) {
            $content->display = request('display');
            $content->update_time = time();
            $content->save();
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 内容下用户黑名单管理
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopBlackByContent()
    {
        $this->validateWith([
            'shop_id'    =>   'required|alpha_dash',
            'black'      =>   'required|numeric|in:0,1',
            'content_id' =>   'required|alpha_dash'
        ]);
        Shop::where('hashid',request('shop_id'))->update(['is_black'=>request('black')]);
        if(intval(request('black'))==1){
            Redis::sadd('black:shop',request('shop_id'));
        }else{
            Redis::srem('black:shop',request('shop_id'));
        }
        $course = Course::where('hashid',request('content_id'))->first();
        if ($course) {
            $state = request('black') == 1 ? 0 : 1;
            $course->state = $state;
            $course->save();
        } else {
            $state = request('black') == 1 ? 2 : 0;
            Content::where('hashid',request('content_id'))->update(['state'=>$state,'update_time'=>time()]);
        }
        return $this->output(['success'=>1]);
    }

    public function getLiveMessage()
    {
        $shop_id = request('shop_id');
        $content_id = request('content_id');
        $count = request('count') ?: 20;
        $msg = AliveMessage::where([
            'shop_id' => $shop_id,
            'content_id' => $content_id,
        ])->orderBy('id','asc')->paginate($count);
        if($msg){
            foreach ($msg->items() as $item){
                if($item->type ==  3 && $item->audio){
                    $item->audio = unserialize($item->audio) ?: [];
                }
                if($item->type ==  2 && $item->indexpic){
                    $indexpic = unserialize($item->indexpic) ?: [];
                    $item->indexpic = is_array($indexpic) ? $indexpic['host'].$indexpic['file'] : $item->indexpic;
                }
                $item->create_time = date('H:i:s',$item->time);
            }
        }
        return $this->output($this->listToPage($msg));
    }
}