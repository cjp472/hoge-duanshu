<?php
/**
 * 轮播图管理
 */
namespace App\Http\Controllers\Admin\Banner;

use App\Events\AppEvent\AppBannerAddEvent;
use App\Events\AppEvent\AppBannerDeleteEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Banner;
use App\Models\Column;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Type;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BannerController extends BaseController
{
    /**
     * @return mixed
     * banner列表
     */
    public function lists()
    {
        $this->validateWithAttribute([
            'type'      => 'alpha_dash|in:new,column,navigation',
            'type_id'   => 'required_if:type,navigation|numeric'
        ],[
            'type'      => '轮播图类型',
            'type_id'  => '导航分类id'
        ]);
        $list = $this->selectList();
        $response = $this->formatList($list);
        return $this->output($this->listToPage($response));
    }

    /**
     * @param $id
     * @return mixed
     * banner详情
     */
    public function detail($id)
    {
        $detail = $this->selectDetail($id);
        $response = $this->formatDetail($detail);
        return $this->output($response);
    }

    /**
     * @return mixed
     * banner新增
     */
    public function create()
    {
        $this->validateBanner();
        $banner = $this->formatBanner();
        $id = $this->createBanner($banner);
        Cache::forget('banner:'.$this->shop['id']);  //清除redis里面banner图的值
        !request('type') && event(new AppBannerAddEvent($this->shop['id'],$id,request('title'),request('indexpic'),request('link')));
        return $this->output($this->getResponse($id));
    }

    /**
     * @return mixed
     * banner更新
     */
    public function update()
    {
        $this->validateWith(['id'=>'required']);
        $this->validateBanner();
        $banner = $this->formatBanner(1);
        $this->updateBanner($banner);
        Cache::forget('banner:'.$this->shop['id']);  //清除redis里面banner图的值
        !request('type') && event(new AppBannerAddEvent($this->shop['id'],request('id'),request('title'),request('indexpic'),request('link')));
        return $this->output($this->getResponse(request('id')));
    }

    /**
     * @param $id
     * @return mixed
     * banner删除
     */
    public function delete($id)
    {
        $banner = Banner::where('shop_id',$this->shop['id'])->findOrFail($id);
        $banner->delete();
        Cache::forget('banner:'.$this->shop['id']);  //清除redis里面banner图的值
        event(new AppBannerDeleteEvent($this->shop['id'],$id));
        return $this->output(['success'=>1]);
    }

    /**
     * banner置顶
     */
    public function top(){
        $this->validateWithAttribute(['id'=>'required','top'=>'required'],['id'=>'轮播图id','top'=>'置顶状态']);
        $banner = Banner::findOrFail(request('id'));
        Cache::forget('banner:'.$this->shop['id']);  //清除redis里面banner图的值
        if($banner){
            $banner->top = request('top');
            $banner->update_time = time();
            $banner->update_user = $this->user['id'];
            $banner->top_time = time();
            $banner->saveOrFail();
            return $this->output(['success'=>1]);
        }
        return $this->error('no_banner');
    }


    /**
     * @return mixed
     * banner上下架
     */
    public function shelf()
    {
        $this->validateWithAttribute(['id'=>'required','state'=>'required'],['id'=>'轮播图id','state'=>'上下架状态']);
        $update = ['state'=>request('state'),'update_time'=>time()];
        $banner = Banner::where(['shop_id'=>$this->shop['id'],'id'=>request('id')])->select('title','indexpic','link')->firstOrFail();
        if(request('state')==1){
            $update['up_time'] = time();
            $bannerNum = $this->selectBannerNum();
            $bannerNum >= 8 && $this->error('exceed_banner_limit');
            $banner = Banner::where(['shop_id'=>$this->shop['id'],'id'=>request('id')])->select('title','indexpic','link','order_id','type')->first();
            $banner->type == 'default' && event(new AppBannerAddEvent($this->shop['id'],request('id'),$banner->title,$banner->indexpic,unserialize($banner->link)));
            //排序到第一位
            $order_id = Banner::where(['shop_id' => $this->shop['id'], 'type' => $banner->type])
                ->orderBy('order_id')
                ->orderByDesc('top')
                ->orderByDesc('update_time')
                ->pluck('id');
            $old_order = $banner->order_id ?: (isset(array_flip($order_id->toArray())[request('id')]) ? array_flip($order_id->toArray())[request('id')] + 1 : 0);
            hg_sort($order_id, request('id'), 0, $old_order, 'banner');
        } else {
            $banner->type == 'default' && event(new AppBannerDeleteEvent($this->shop['id'],request('id')));
        }
        request('state')==0 && $update['down_time'] = time();
        Banner::where(['id'=>request('id'),'shop_id'=>$this->shop['id']])->update($update);
        Cache::forget('banner:'.$this->shop['id']);  //清除redis里面banner图的值

        return $this->output(['success'=>1]);
    }

    //获取banner列表
    private function selectList(){
        $type = request('type') ? : 'home';
        $sql = Banner::where(['shop_id'=>$this->shop['id'],'type'=>$type]);
        if(request('type_id')){
            $sql->where('type_id',request('type_id'));
        }
        $state = request('state');
        if($state || isset($state)){
            $sql->where('state',intval(request('state')));
        }
        request('title') && $sql->where('title','like','%'.request('title').'%');
        $list = $sql->orderBy('order_id')
            ->orderByDesc('top')
            ->orderByDesc('update_time')
            ->paginate(request('count')?:10);
        return $list;
    }

    //获取banner详情
    private function selectDetail($id){
        $banner = Banner::where('shop_id',$this->shop['id'])->findOrFail($id);
        return $banner;
    }

    private function selectBannerNum(){
        return Banner::where(['shop_id'=>$this->shop['id'],'state'=>1,'type'=>request('type')? : 'home'])->count();
    }

    //新增banner
    private function createBanner($banner){
        if($banner['state']==1){
            $bannerNum = $this->selectBannerNum();
            $bannerNum >= 8 && $this->error('exceed_banner_limit');
        }
        $id = Banner::insertGetId($banner);
        return $id;
    }

    //获取返回数据
    private function getResponse($id){
        $return['data'] = Banner::where(['id'=>$id,'shop_id'=>$this->shop['id']])->firstOrFail();
        return $return;
    }

    //更新banner
    private function updateBanner($data){
       Banner::where(['id'=>request('id'),'shop_id'=>$this->shop['id']])->update($data);
    }

    //处理banner列表
    private function formatList($list){
        if($list){
            foreach ($list as $k=>$v){
                $v->up_time > time() && $v->state = 0;
                $v->up_time = date('Y-m-d H:i:s',$v->up_time);
                $v->down_time = date('Y-m-d H:i:s',$v->down_time);
                $v->create_time = date('Y-m-d H:i:s',$v->create_time);
                $v->update_time = date('Y-m-d H:i:s',$v->update_time);
                $v->link = unserialize($v->link);
                $v->indexpic = hg_unserialize_image_link($v->indexpic);
                $v->is_lock = intval($v->is_lock);
                $v->state = intval($v->state);
                $v->top =intval($v->top);
            }
        }
        return $list?:[];
    }

    //处理banner详情
    private function formatDetail($data){
        if($data){
            $data->up_time = date('Y-m-d H:i:s',$data->up_time);
            $data->down_time = date('Y-m-d H:i:s',$data->down_time);
            $data->link = unserialize($data->link);
            $data->indexpic = hg_unserialize_image_link($data->indexpic);
            $return['data'] = $data;
        }
        return $return?:[];
    }

    //banner新增数据验证
    private function validateBanner(){
        $this->validateWithAttribute([
            'title'     => 'required|max:20',
            'indexpic'  => 'required',
            'link'      => 'required|array',
            'state'     => 'required',
            'type'      => 'alpha_dash|in:new,column,navigation',
            'type_id'   => 'required_if:type,navigation|numeric'
        ],[
            'title'     => '轮播图标题',
            'indexpic'  => '图片',
            'link'      => '跳转链接',
            'state'     => '保存状态',
            'type'      => '轮播图分类'
        ]);
    }

    //处理banner数据
    private function formatBanner($sign=''){
        $banner = [
            'shop_id'       => $this->shop['id'],
            'title'         => request('title'),
            'indexpic'      => hg_explore_image_link(request('indexpic')),
            'link'          => serialize(request('link')),
            'update_user'   => 0,
            'update_time'   => time(),
            'state'         => request('state'),
            'top'           => 1,
            'type'          => request('type') ? : 'home',
        ];
        switch(request('type')){
            case 'new':
                $type = request('link.type');
                $content = Content::where(['shop_id'=>$this->shop['id'],'hashid'=>request('link.id'),'type'=>$type])->value('id');
                if(!$content || !in_array($type,['article','audio','video'])){
                    $this->error('content-not-match');
                }
                break;
            case 'column':
                $column = Column::where(['shop_id'=>$this->shop['id'],'hashid'=>request('link.id')])->value('id');
                if(!$column){
                    $this->error('content-not-match');
                }
                break;
            case 'navigation':
                $type_id = request('type_id');
                $type = Type::where('shop_id',$this->shop['id'])->find($type_id);
                $content_type = ContentType::where(['content_id'=>request('link.id'),'type'=>request('link.type'),'type_id'=>$type_id])->value('id');
                if(!$content_type || !$type){
                    $this->error('content-not-match');
                }
                $banner['type_id'] = $type_id;
                break;
            default:

                break;
        }
        //如果是导航的，需设置导航id
//        request('type')=='navigation' && $banner['type_id'] = request('type_id');
        !$sign && $banner['create_user'] = $this->user['id'];
        !$sign && $banner['create_time'] = time();
        $sign && $banner['update_user'] = $this->user['id'];
        $sign && $banner['update_time'] = time();
        return $banner;
    }

    /**
     * 轮播图排序
     * @return \Illuminate\Http\JsonResponse
     */
    public function sort(){
        $this->validateWithAttribute([
            'id' => 'required|numeric',
            'order' => 'required|numeric',
            'type'  => 'required|alpha_dash'
        ], [
            'id' => '轮播图id',
            'order' => '排序位置',
            'type'  => 'banner类型'
        ]);

        $state = Banner::findOrFail(request('id'))->value('state');

        $order_id = Banner::where(['shop_id' => $this->shop['id'],'type'=>request('type'),'state'=>$state])
            ->orderBy('order_id')
            ->orderByDesc('top')
            ->orderByDesc('update_time')
            ->pluck('id');
        $old_order = Banner::findOrFail(request('id')) ? Banner::find(request('id'))->order_id : (isset(array_flip($order_id->toArray())[request('id')]) ? array_flip($order_id->toArray())[request('id')] +1 : 0);
        hg_sort($order_id,request('id'),request('order'),$old_order,'banner');
        Cache::forget('banner:'.$this->shop['id']);  //清除redis里面banner图的值
        
        return $this->output(['success'=>1]);
    }

}