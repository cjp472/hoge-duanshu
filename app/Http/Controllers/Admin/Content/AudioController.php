<?php
/**
 * 音频内容
 */
namespace App\Http\Controllers\Admin\Content;

use App\Events\AppEvent\AppContentDeleteEvent;
use App\Models\Audio;
use App\Models\Content;
use App\Models\PromotionContent;
use Illuminate\Support\Facades\Cache;


class AudioController extends ContentController
{
    /**
     * 列表
     */
    public function lists()
    {
        $list = $this->selectList('audio');
        $response = $this->formatList($list);
        return $this->output($response);
    }

    /**
     * 编辑详情接口
     */
    public function detail($id)
    {
        $detail = $this->get_detail($id);
        $response = $this->formatDetail($detail);
        return $this->output($response);
    }

    /**
     * 新增
     */
    public function create()
    {
        $this->validateBaseContent();
        $this->validateAudio();
        $param = $this->formatBaseContent();
        $param['type'] = 'audio';
        $hashid = $this->createBaseContent($param);
        $this->createAudio($hashid);
        $data = ['content_id'=>$hashid,'type_id'=>request('type_id'),'type'=>'audio'];
        $this->createOrUpdateType($data);
        return $this->output($this->getResponse($hashid));
    }

    /**
     * 更新
     */
    public function update()
    {
        $this->checkId('audio');
        $this->validateBaseContent();
        $param = $this->formatBaseContent(1);
        Cache::forget('content:'.$this->shop['id'].':'.request('id'));
        $this->updateBaseContent($param);
        $this->updateAudio();
        $data = ['content_id'=>request('id'),'type_id'=>request('type_id'),'type'=>'audio'];
        $this->createOrUpdateType($data);
        $response = $this->getResponse(request('id'));
        return $this->output($response);
    }

    /**
     * 删除
     */
    public function delete()
    {
        $params = $this->checkParam('audio');
        Content::where('shop_id',$this->shop['id'])->whereIn('hashid',$params)->delete();
        Audio::whereIn('content_id',$params)->delete();
        PromotionContent::where('content_type','audio')->whereIn('content_id',$params)->delete();
        Cache::forget('h5:new:content:list:'.$this->shop['id']);   //新增或更新  最新内容时  清除缓存
        $this->deleteType($params);
        $this->deletePayment($params,'audio');//删除该内容对应的订阅数据
        //为了不破坏事件里面的逻辑
        foreach($params as $value){
            $data = ['content_id'=>$value,'shop_id'=>$this->shop['id'],'type'=>'audio'];
            event(new AppContentDeleteEvent($data));
        }
        return $this->output(['success'=>1]);
    }

    private function validateAudio(){
        $this->validateWithAttribute([
            'content'=>'required',
            'url'=>'required',
            'file_name'=>'required',
//            'brief'=>'required',
        ],[
            'content'=>'音频详情',
            'url'=>'音频链接',
            'file_name'=>'音频名称',
            'brief'=>'音频简介'
        ]);
    }

    private function createAudio($hashid){
        Audio::insert([
            'content_id' => $hashid,
            'content' => request('content'),
            'file_name' => request('file_name'),
            'url' => request('url')?:'',
            'size' => request('size')?:0,
            'test_url' => request('test_url')?:'',
            'test_file_name' => request('test_file_name')?:'',
            'test_size' => request('test_size')?:0,
        ]);
        Content::where('hashid',$hashid)->update(['brief'=>request('brief')]);
    }

    private function updateAudio(){
        Audio::where('content_id',request('id'))->update([
            'content'=>request('content'),
            'file_name' => request('file_name'),
            'url'=>request('url')?:'',
            'size' => request('size')?:0,
            'test_url' => request('test_url')?:'',
            'test_file_name' => request('test_file_name')?:'',
            'test_size' => request('test_size')?:0,
        ]);
        Content::where('hashid',request('id'))->update(['brief'=>request('brief')]);
    }


    //获取音频内容详情
    private function get_detail($id){
        $data = Content::join('audio','audio.content_id','=','content.hashid')
            ->where(['content.hashid'=>$id,'content.shop_id'=>$this->shop['id']])
            ->select('content.type','content.id','content.hashid','content.title','content.indexpic','content.state','audio.content',
                'audio.file_name','audio.test_file_name','audio.url','audio.test_url','audio.size','audio.test_size','content.payment_type',
                'content.column_id','content.price','content.brief','content.up_time','content.is_test',
                'content.view_count','content.unique_member','content.sales_total', 'content.comment_count')
            ->firstOrFail();
        return $data?:[];
    }

}