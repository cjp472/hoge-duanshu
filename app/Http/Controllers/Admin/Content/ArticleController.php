<?php
/**
 * 图文内容
 */
namespace App\Http\Controllers\Admin\Content;

use App\Events\AppEvent\AppContentDeleteEvent;
use App\Models\Article;
use App\Models\Content;
use App\Models\PromotionContent;
use Illuminate\Support\Facades\Cache;


class ArticleController extends ContentController
{
    /**
     * 列表
     */
    public function lists()
    {
        $list = $this->selectList('article');
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
        $this->validateContent();
        $param = $this->formatBaseContent();
        $param['type'] = 'article';
        $hashid = $this->createBaseContent($param);
        $this->createArticle($hashid);
        $data = ['content_id'=>$hashid,'type_id'=>request('type_id'),'type'=>'article'];
        $this->createOrUpdateType($data);
        return $this->output($this->getResponse($hashid));
    }

    /**
     * 更新
     */
    public function update()
    {
        $this->checkId('article');
        $this->validateBaseContent();
        $this->validateContent();
        $param = $this->formatBaseContent(1);
        Cache::forget('content:'.$this->shop['id'].':'.request('id'));
        $this->updateBaseContent($param);
        $this->updateArticle();
        $data = ['content_id'=>request('id'),'type_id'=>request('type_id'),'type'=>'article'];
        $this->createOrUpdateType($data);
        $response = $this->getResponse(request('id'));
        return $this->output($response);
    }

    /**
     * 删除
     */
    public function delete()
    {
        $params = $this->checkParam('article');
        Content::where('shop_id',$this->shop['id'])->whereIn('hashid',$params)->delete();
        Article::whereIn('content_id',$params)->delete();
        PromotionContent::where('content_type','article')->whereIn('content_id',$params)->delete();
        Cache::forget('h5:new:content:list:'.$this->shop['id']);   //新增或更新  最新内容时  清除缓存
        $this->deleteType($params);
        $this->deletePayment($params,'article');//删除该内容对应的订阅数据
        //为了不破坏事件里面的逻辑
        foreach($params as $value){
            $data = ['content_id'=>$value,'shop_id'=>$this->shop['id'],'type'=>'article'];
            event(new AppContentDeleteEvent($data));
        }
        return $this->output(['success'=>1]);
    }

    private function validateContent(){
        $this->validateWithAttribute(['content'=>'required'],['content'=>'图文内容']);
    }

    //获取图文详情
    private function selectDetail($id){
        $content = Content::join('article','article.content_id','=','content.hashid')
            ->where(['content.hashid'=>$id,'content.shop_id'=>$this->shop['id']])
            ->select('content.type','content.id','content.hashid','content.title','content.indexpic','content.state',
                'article.content','content.brief','content.payment_type','content.column_id','content.price','content.up_time','content.is_test',
                'content.view_count','content.unique_member','content.sales_total', 'content.comment_count')
            ->firstOrFail();
        return $content?:[];
    }


    private function createArticle($hashid)
    {
        Article::insert(['content_id' => $hashid, 'content' => request('content')]);
        Content::where('hashid',$hashid)->update(['brief'=>request('brief')]);
    }


    private function updateArticle()
    {
        Article::where('content_id', request('id'))->update(['content' => request('content')]);
        Content::where('hashid',request('id'))->update(['brief'=>request('brief')]);
    }

}