<?php
/**
 * 视频内容
 */
namespace App\Http\Controllers\Admin\Content;

use App\Events\AppEvent\AppContentDeleteEvent;
use App\Models\Content;
use App\Models\Video;
use App\Models\Videos;
use App\Models\PromotionContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class VideoController extends ContentController
{
    /**
     * 列表
     */
    public function lists()
    {
        $list = $this->selectList('video');
        $response = $this->formatList($list);
        return $this->output($response);
    }

    /**
     * 编辑详情接口
     */
    public function detail($id)
    {
        $detail = $this->selectDetail($id);
        $response = $this->formatDetail($detail);
        return $this->output($response);
    }

    /**
     * 新增
     */
    public function create()
    {
        $this->validateBaseContent();
        $this->validateVideo();
        $param = $this->formatBaseContent();
        $param['type'] = 'video';
        $hashid = $this->createBaseContent($param);
        $this->createVideo($hashid);
        $data = ['content_id'=>$hashid,'type_id'=>request('type_id'),'type'=>'video'];
        $this->createOrUpdateType($data);
        $response = $this->getResponse($hashid);
        return $this->output($response);
    }

    /**
     * 更新
     */
    public function update()
    {
        $this->checkId('video');
        $this->validateBaseContent();
        $this->validateVideo();
        $param = $this->formatBaseContent(1);
        Cache::forget('content:'.$this->shop['id'].':'.request('id'));
        $this->updateBaseContent($param);
        $this->updateVideo();
        $data = ['content_id'=>request('id'),'type_id'=>request('type_id'),'type'=>'video'];
        $this->createOrUpdateType($data);
        $response = $this->getResponse(request('id'));
        return $this->output($response);
    }

    /**
     * 删除
     */
    public function delete()
    {
        $params = $this->checkParam('video');
        Content::where('shop_id',$this->shop['id'])->whereIn('hashid',$params)->delete();
        Video::whereIn('content_id',$params)->delete();
        PromotionContent::where('content_type','video')->whereIn('content_id',$params)->delete();
        Cache::forget('h5:new:content:list:'.$this->shop['id']);   //删除  最新内容时  清除缓存
        $this->deleteType($params);
        $this->deletePayment($params,'video');//删除该内容对应的订阅数据
        //为了不破坏事件里面的逻辑
        foreach($params as $value){
            $data = ['content_id'=>$value,'shop_id'=>$this->shop['id'],'type'=>'video'];
            event(new AppContentDeleteEvent($data));
        }
        return $this->output(['success'=>1]);
    }

    private function validateVideo(){
        $this->validateWithAttribute([
            'file_id'=>'required',
            'file_name'=>'required',
            'content'=>'required',
            'size' => 'required',
        ],[
            'file_id'=>'视频id',
            'file_name'=>'视频名称',
            'content'=>'视频详情',
            'size'=>'视频大小',
        ]);
    }

    private function createVideo($hashid)
    {
        Video::insert([
            'content_id' => $hashid,
            'content' => request('content'),
            'patch' => request('patch') ? hg_explore_image_link(request('patch')) : hg_explore_image_link(request('indexpic')),
            'file_id' => request('file_id') ?: 0,
            'file_name' => request('file_name') ?: '',
            'size' => request('size')?:0,
            'test_file_id' => request('test_file_id') ?: 0,
            'test_file_name' => request('test_file_name') ?: '',
            'test_size' => request('test_size')?:0,
        ]);

        $status = Videos::where('file_id', request('file_id'))->value('status');
        $transcode = $status ? $status : 2;
        Video::where('file_id', request('file_id'))->update(['transcode' => $status ?: 0]);

        $update = ['brief' => request('brief')];
        // 未转码成功的不上架
        if (!$transcode) {
            $update['state'] = 2;
        }
        Content::where('hashid', $hashid)->update($update);
    }

    private function updateVideo(){
        Video::where('content_id',request('id'))->update([
            'content'=>request('content'),
            'file_id'=>request('file_id')?:0,
            'file_name' => request('file_name') ?: '',
            'patch' => request('patch') ? hg_explore_image_link(request('patch')) : hg_explore_image_link(request('indexpic')),
            'size' => request('size')?:0,
            'test_file_id' => request('test_file_id') ?: 0,
            'test_file_name' => request('test_file_name') ?: '',
            'test_size' => request('test_size')?:0,
        ]);
        $status = Videos::where('file_id', request('file_id'))->value('status');
        if($status){
            $transcode = $status == 0 ? 1: 2;     //转码状态
        }else{
            $transcode = 2;
        }
        Video::where('file_id',request('file_id'))->update(['transcode'=>$transcode]);

        $update = ['brief' => request('brief')];
        // 未转码成功的不上架
        if (!$transcode) {
            $update['state'] = 2;
        }

        Content::where('hashid',request('id'))->update($update);
    }

    private function selectDetail($id){
        $data = Content::join('video','video.content_id','=','content.hashid')
            ->where(['content.hashid'=>$id,'content.shop_id'=>$this->shop['id']])
            ->select('content.type','content.id','content.hashid','content.title','content.indexpic','content.state','video.content','video.patch',
                'video.file_id','video.file_name','video.size','video.test_file_id','video.test_file_name','video.test_size','content.payment_type','content.column_id',
                'content.price','content.brief','content.up_time','content.is_test',
                'content.view_count','content.unique_member','content.sales_total', 'content.comment_count')
            ->firstOrFail();
        $data->patch = hg_unserialize_image_link($data->patch);
        return $data?:[];
    }

}