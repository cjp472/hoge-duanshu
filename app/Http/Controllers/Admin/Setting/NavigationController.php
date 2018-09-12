<?php
namespace App\Http\Controllers\Admin\Setting;

use App\Models\Type;
use App\Models\Course;
use App\Models\Column;
use App\Models\Content;
use App\Models\Community;
use App\Models\Navigation;
use App\Models\MemberCard;
use App\Models\ContentType;
use App\Models\LimitPurchase;
use App\Http\Controllers\Admin\BaseController;


class NavigationController extends BaseController
{
    const PAGINATE = 20;
    const NAVIGATION_MAX_NUMBER = 8;
    /**
     * 分类新增
    */
    public function createClass()
    {
        $this->validateWithAttribute([
            'title' => 'required|string',
            'index_pic' => 'required'
        ],[
            'title' => '标题',
            'index_pic' => '封面图'
        ]);
        $shopId = $this->shop['id'];
        $data = [
            'title' => request('title'),
            'indexpic' => hg_explore_image_link(request('index_pic')),
            'brief' => request('brief')?:'',
            'shop_id' => $shopId
        ];
        Type::insert($data);
        return $this->output(['success'=>1]);
    }

    /**
     * 分类列表
    */
    public function classLists()
    {
        $shopId = $this->shop['id'];
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $title = request('title');
        $obj = Type::select('id','title','create_time','indexpic')
            ->where('shop_id',$shopId)
            ->when($title,function($query) use($title) {
                $query->where('title','LIKE','%'.$title.'%');
            })
            ->orderBy('order_id','asc')
            ->orderBy('create_time','desc')
            ->paginate($count);
        $result = $this->listToPage($obj);
        if($info = $result['data']){
            foreach($info as $val){
                $val->indexpic = $val->indexpic ? unserialize($val->indexpic) : '';
                $val->child = ContentType::where('type_id',$val->id)->distinct()->count('content_id');
            }
        }
        return $this->output($result);
    }

    /**
     * 分类删除
    */
    public function classDelete($id)
    {
        $obj = Type::find($id);
        if($obj){
            $obj->delete();
            ContentType::where('type_id',$id)->delete();
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 分类更新
    */
    public function classUpdate($id)
    {
        $this->validateWithAttribute([
            'title'    => 'string',
//            'index_pic' => 'required'
        ],[
            'title' => '标题',
            'index_pic' => '封面图'
        ]);
        $obj = Type::find($id);
        if($obj){
            $obj->title = request('title');
            $obj->indexpic = hg_explore_image_link(request('index_pic'));
            $obj->brief = request('brief')?:'';
            $obj->save();
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 分类详情
     */
    public function classDetail($id)
    {
        $obj = Type::select('id','title','indexpic','brief')->find($id);
        if($obj){
            $count = request('count') ? intval(request('count')) : self::PAGINATE;
            $number = ContentType::where('type_id',$id)->distinct()->count('content_id');
            $info = ContentType::where('type_id',$id)
                ->orderBy('order_id')
                ->orderBy('create_time','desc')
                ->paginate($count);
            $result = $this->listToPage($info);
            if($data = $result['data']){
                foreach($data as $value){
                    if(in_array($value->type,['article','audio','video','live'])){
                        $value->content_name = Content::where(['hashid'=>$value->content_id,'type'=>$value->type])->value('title');
                    }elseif('column' == $value->type){
                        $value->content_name = Column::where('hashid',$value->content_id)->value('title');
                    }elseif('course' == $value->type){
                        $value->content_name = Course::where('hashid',$value->content_id)->value('title');
                    }
                    $value->create_time = hg_format_date($value->create_time);
                }
            }
            $result['extra_data'] = [
                'title' => $obj->title,
                'index_pic' => $obj->indexpic ? unserialize($obj->indexpic) : '',
                'brief' => $obj->brief,
                'count' => $number
            ];
            return $this->output($result);
        }
        $this->error('data-not-fond');
    }

    /**
     * 分类数据删除
    */
    public function deleteClassContent()
    {
        $id = request('id');
        $ids = explode(',',$id);
        ContentType::whereIn('id',$ids)->delete();
        return $this->output(['success'=>1]);
    }

    /**
     * 分类数据排序
    */
    public function sortClassContent()
    {
        $this->validateWithAttribute([
            'id'    => 'required',
            'order' => 'required',
            'type_id' => 'required'
        ],[
            'id'    => '标识',
            'order' => '序号',
            'type_id' => '分类id'
        ]);
        $orderId = ContentType::where('id',request('id'))->value('order_id');
        $ids = ContentType::where('type_id',request('type_id'))->orderBy('order_id')->orderBy('create_time','desc')->pluck('id');
        hg_sort($ids,request('id'),request('order'),$orderId,'contentType');
        return $this->output(['success'=>1]);
    }

    /**
     * 分类排序
    */
    public function sortClass()
    {
        $this->validateWithAttribute([
            'id'    => 'required',
            'order' => 'required'
        ],[
            'id'    => '标识',
            'order' => '序号'
        ]);
        $orderId = Type::where('id',request('id'))->value('order_id');
        $shopId = $this->shop['id'];
        $ids = Type::where('shop_id',$shopId)->orderBy('order_id','asc')->orderBy('create_time','desc')->pluck('id');
        hg_sort($ids,request('id'),request('order'),$orderId,'type');
        return $this->output(['success'=>1]);
    }

    /**
     * 导航新增
    */
    public function create()
    {
        $this->validateWithAttribute([
            'title'    => 'required|max:12',
            'index_pic' => 'required',
            'link' => 'required'
        ],[
            'title'    => '导航标题',
            'index_pic' => '导航图',
            'link' => '跳转'
        ]);
        $shopId = $this->shop['id'];
        $count = Navigation::where('shop_id',$shopId)->count();
        if(self::NAVIGATION_MAX_NUMBER < $count){
            $this->error('navigation_is_max');
        }
        $data = [
            'shop_id'     => $shopId,
            'title'       => request('title'),
            'index_pic'    => hg_explore_image_link(request('index_pic')),
            'link'        => serialize(request('link')),
            'create_time' => time()
        ];
        Navigation::insert($data);
        return $this->output(['success'=>1]);
    }

    /**
     * 导航列表
    */
    public function lists()
    {
        $shopId = $this->shop['id'];
        $obj = Navigation::where('shop_id',$shopId)
            ->orderBy('order_id')
            ->orderBy('create_time','desc')
            ->get();
        if($obj ){
            foreach ($obj->all() as $item){
                $item->index_pic = $item->index_pic ? unserialize($item->index_pic) : [];
                $item->link = $item->link ? unserialize($item->link) : [];
            }
        }
        return $this->output($obj);
    }

    /**
     * 导航修改
    */
    public function update()
    {
        $this->validateWithAttribute([
            'id' => 'required',
            'title'    => 'required|max:12',
            'index_pic' => 'required',
            'link' => 'required'
        ],[
            'id' => '标识',
            'title'    => '导航标题',
            'index_pic' => '导航图',
            'link' => '跳转'
        ]);
        $shopId = $this->shop['id'];
        $obj = Navigation::where(['id'=>request('id'),'shop_id'=>$shopId])->first();
        if($obj){
            $obj->title = request('title');
            $obj->index_pic = hg_explore_image_link(request('index_pic'));
            $obj->link = serialize(request('link'));
            $obj->save();
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 导航删除
    */
    public function delete()
    {
        $this->validateWithAttribute([
            'id' => 'required'
        ],[
            'id' => '标识'
        ]);
        $ids = explode(',',request('id'));
        $shopId = $this->shop['id'];
        Navigation::whereIn('id',$ids)->where('shop_id',$shopId)->delete();
        return $this->output(['success'=>1]);
    }

    /**
     * 导航状态修改
    */
    public function status()
    {
        $this->validateWithAttribute([
            'id' => 'required',
            'status' => 'required|in:1,2'
        ],[
            'id' => '标识',
            'status' => '状态'
        ]);

        $shopId = $this->shop['id'];
        $obj = Navigation::where(['id'=>request('id'),'shop_id'=>$shopId])->first();
        if($obj){
            $obj->status = request('status');
            $obj->save();
        }
        return $this->output(['success'=>1]);
    }

    /**
     * 导航排序
    */
    public function sort()
    {
        $this->validateWithAttribute([
            'id' => 'required',
            'order' => 'required|numeric'
        ],[
            'id' => '标识',
            'order' => '序号'
        ]);
        $shopId = $this->shop['id'];
        $ids = Navigation::where(['shop_id'=>$shopId])->orderBy('order_id')->orderBy('create_time','desc')->pluck('id');
        $orderId = Navigation::where(['id'=>request('id'),'shop_id'=>$shopId])->value('order_id');
        hg_sort($ids,request('id'),request('order'),$orderId,'navigation');
        return $this->output(['success'=>1]);
    }

    /**
     * 导航详情
    */
    public function detail()
    {
        $this->validateWithAttribute([
            'id' => 'required',
        ],[
            'id' => '标识',
        ]);
        $shopId = $this->shop['id'];
        $obj = Navigation::select('id','index_pic','title','link')->where(['id'=>request('id'),'shop_id'=>$shopId])->first();
        $obj->index_pic = $obj->index_pic ? unserialize($obj->index_pic) : [];
        $obj->link = $obj->link ? unserialize($obj->link) : [];
        return $this->output($obj);
    }

    /**
     * 内容选择
    */
    public function contents()
    {
        //其它类型待增加
        $this->validateWithAttribute([
            'type' => 'required|in:column,course,member_card,class,article,audio,video,live,limit_purchase,community,promoter',
        ],[
            'type' => '类型',
        ]);

        $shopId = $this->shop['id'];
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $title = request('title');
        switch(request('type')){
            case 'column':
                $data = Column::select('hashid as content_id', 'title','create_time')
                    ->where(['shop_id' => $shopId, 'state' => 1])
                    ->orderBy('create_time', 'desc');
                if($title){
                    $data->where('title','like','%'.$title.'%');
                }
                $data = $data->paginate($count);
                $result = $this->listToPage($data);
                if ($result['data']) {
                    foreach ($result['data'] as $item) {
                        $item->type = 'column';
                        $item->create_time = hg_format_date($item->create_time);
                    }
                }
                break;
            case 'course':
                $data = Course::select('hashid as content_id','course_type', 'title', 'create_time')
                    ->where(['shop_id' => $shopId, 'is_lock' => 0])
                    ->orderBy('create_time', 'desc');
                if($title){
                    $data->where('title','like','%'.$title.'%');
                }
                $data = $data->paginate($count);
                $result = $this->listToPage($data);
                if ($result['data']) {
                    foreach ($result['data'] as $item) {
                        $item->type = 'course';
                        $item->create_time = hg_format_date($item->create_time);
                    }
                }
                break;
            case 'member_card':
                $data = MemberCard::select('hashid as content_id','title','created_at','up_time')
                    ->where(['shop_id'=>$shopId,'is_del'=>0])
                    ->orderBy('created_at', 'desc');
                if($title){
                    $data->where('title','like','%'.$title.'%');
                }
                $data = $data->paginate($count);
                $result = $this->listToPage($data);
                if ($result['data']) {
                    foreach ($result['data'] as $item) {
                        $item->type = 'member_card';
                        $item->create_time = hg_format_date($item->up_time);
                    }
                }
                break;
            case 'class':
                $data = Type::select('id as content_id','title','create_time')
                    ->where('shop_id',$shopId)
                    ->orderBy('create_time', 'desc');
                if($title){
                    $data->where('title','like','%'.$title.'%');
                }
                $data = $data->paginate($count);
                $result = $this->listToPage($data);
                if ($result['data']) {
                    foreach ($result['data'] as $item) {
                        $item->type = 'class';
                    }
                }
                break;
            case 'limit_purchase':
                $data = LimitPurchase::select('hashid as content_id','title','created_at')
                    ->where('shop_id',$shopId)
                    ->orderBy('created_at', 'desc');
                if($title){
                    $data->where('title','like','%'.$title.'%');
                }
                $data = $data->paginate($count);
                $result = $this->listToPage($data);
                if ($result['data']) {
                    foreach ($result['data'] as $item) {
                        $item->type = 'limit_purchase';
                    }
                }
                break;
            case 'community':
                $data = Community::select('hashid as content_id','title','created_at')
                    ->where('shop_id',$shopId)
                    ->orderBy('created_at', 'desc');
                if($title){
                    $data->where('title','like','%'.$title.'%');
                }
                $data = $data->paginate($count);
                $result = $this->listToPage($data);
                if ($result['data']) {
                    foreach ($result['data'] as $item) {
                        $item->type = 'community';
                    }
                }
                break;
            default:
                $data = Content::select('hashid as content_id', 'title', 'type', 'create_time')
                    ->where(['shop_id' => $shopId, 'type' => request('type'), 'is_lock' => 0])
                    ->where('payment_type', '!=', 1)//筛选不属于专栏的
                    ->where('state', '!=', 2)//筛选下架的
                    ->where('up_time', '<', time())
                    ->orderBy('create_time', 'desc');
                if($title){
                    $data->where('title','like','%'.$title.'%');
                }
                $data = $data->paginate($count);
                $result = $this->listToPage($data);
                if ($result['data']) {
                    foreach ($result['data'] as $item) {
                        $item->create_time = hg_format_date($item->create_time);
                    }
                }
                break;
        }
        return $this->output($result);
    }
}