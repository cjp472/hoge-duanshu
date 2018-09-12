<?php
/**
 * 首页分类导航
 */
namespace App\Http\Controllers\Admin\Setting;

use App\Events\AppEvent\AppTypeAddEvent;
use App\Events\AppEvent\AppTypeDeleteEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Http\Controllers\Admin\OpenPlatform\WXAppletController;
use App\Models\ContentType;
use App\Models\Type;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TypeController extends BaseController
{

    /**
     * 分类列表
     */
    public function lists()
    {
        $data = Type::where('shop_id',$this->shop['id'])
            ->orderBy('order_id','desc')
            ->select('id','title','indexpic','status')
            ->get();
        foreach ($data as $item){
            $item->indexpic = hg_unserialize_image_link($item->indexpic);
            $item->status = intval($item->status);
        }
        return $this->output(['data' => $data]);
    }

    /**
     * 导航分类详情接口
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail(){
        $this->validateWithAttribute([
            'id'    => 'required|numeric',
        ],[
            'id'    => '导航分类id'
        ]);
        $type = Type::where('shop_id',$this->shop['id'])->findOrFail(request('id'),['id','title','indexpic','status']);
        $type->indexpic = hg_unserialize_image_link($type->indexpic);
        $type->status = intval($type->status);
        return $this->output($type);
    }

    /**
     * 新增分类（最多4条数据）
     */
    public function addType(){
        $this->validateWithAttribute([
            'title' => 'required|string',
        ],[
            'title' => '标题',
        ]);
        $result = Type::where('shop_id',$this->shop['id'])
            ->select(DB::raw('count(*) as num'))->first();
        if($result['num'] < 4){
            $type = [];
            $type['title'] = request('title');
            $type['indexpic'] = hg_explore_image_link(request('indexpic'));
            $type['shop_id'] = $this->shop['id'];;
            $id = Type::insertGetId($type);
            Cache::forget('navigation:'.$this->shop['id']);  //清楚redis里面分类导航的值
            event(new AppTypeAddEvent($this->shop['id'],$id,request('title'),request('indexpic')));
            return $this->output(['data' => $type]);
        }else{
            $this->error('addNavigation-fail');
        }
    }

    /**
     * 删除分类
     */
    public function deleteType()
    {
        $this->validateWithAttribute(['id' => 'required|numeric'],['id'=>'分类id']);
        $result = Type::where('id',request('id'))->delete();
        if($result){
            Cache::forget('navigation:'.$this->shop['id']);  //清楚redis里面分类导航的值
            $this->typeContent(request('id'));
            event(new AppTypeDeleteEvent($this->shop['id'],request('id')));
            return $this->output(['success' => 1]);
        }else{
            $this->error('deleteNavigation-fail');
        }
    }

    /**
     * 删除导航里的内容
     *
     * @param $content_id
     */
    protected function typeContent($type_id)
    {
        ContentType::where('type_id',$type_id)->delete();
    }

    /**
     * 更新分类
     */
    public function updateType(){
        $this->validateWithAttribute([
            'id'       => 'required|numeric',
            'title'    => 'string',
        ],[
            'id'    => '分类id',
            'title' => '标题',
        ]);
        $type = Type::find(request('id'));
        request('title') && $type->title = request('title');
        request('indexpic') && $type->indexpic = hg_explore_image_link(request('indexpic'));
        $type->save();
        Cache::forget('navigation:'.$this->shop['id']);  //清楚redis里面分类导航的值
        event(new AppTypeAddEvent($this->shop['id'],request('id'),request('title'),request('indexpic')));
        return $this->output(['data' => $type]);
    }

    /**
     * 修改显示隐藏功能
     */
    public function changeStatus(){
        $this->validateWithAttribute([
            'id'       => 'required|numeric',
            'status'    => 'required|numeric'
        ],[
            'id'       => '分类id',
            'status'    => '状态'
        ]);
        $type = Type::findOrFail(request('id'));
        $type->status = request('status');
        $result = $type->save();
        if (request('status')) {
            event(new AppTypeAddEvent($this->shop['id'],request('id'),$type->title,hg_unserialize_image_link($type->indexpic)));   //同步app新增
        } else {
            event(new AppTypeDeleteEvent($this->shop['id'],request('id'))); //同步app删除
        }
        if($result){
            Cache::forget('navigation:'.$this->shop['id']);  //清楚redis里面分类导航的值
            return $this->output(['success' => 1]);
        }else{
            $this->error('statusNavigation-fail');
        }
    }


    /**
     * 导航分类排序接口
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortType()
    {
        $this->validateWithAttribute([
            'id' => 'required|numeric',
            'order' => 'required|numeric'
        ], [
            'id' => '导航分类id',
            'order' => '排序位置'
        ]);


        $order_id = Type::where(['shop_id' => $this->shop['id']])->orderBy('order_id')->pluck('id');
        $old_order = Type::findOrFail(request('id'))->order_id ? : array_flip($order_id->toArray())[request('id')] +1;

        if($old_order != request('order')) {
            foreach ($order_id as $key => $id) {
                $key++;
                switch (request('order')) {
                    //放首位
                    case 0:
                        if ($key <= $old_order) {
                            $key += 1;
                        }
                        if ($id == request('id')) {
                            $key = 1;
                        }
                        break;
                    //放末尾
                    case -1:
                        if ($old_order < $key) {
                            $key -= 1;
                        }
                        //排序的id=当前id
                        if (request('id') == $id) {
                            //如果超出范围，用范围最大值
                            $key = count($order_id);
                        }
                        break;
                    default :
                        //大=>小
                        if ($old_order > request('order')) {
                            if ($key >= request('order') && $key <= $old_order) {
                                $key += 1;
                            }
                        } //小=>大
                        elseif ($old_order < request('order')) {
                            if ($key <= request('order') && $key >= $old_order) {
                                $key -= 1;
                            }
                        }
                        //排序的id=当前id
                        if (request('id') == $id) {
                            //如果超出范围，用范围最大值
                            $key = count($order_id) < request('order') ? count($order_id) : request('order');
                        }
                        break;
                }
                Type::find($id)->update(['order_id' => $key]);
            }
        }
        return $this->output(['success'=>1]);

    }





}