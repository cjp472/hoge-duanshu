<?php
namespace App\Http\Controllers\H5\Content;

use App\Models\ShopRemindStatus;
use App\Models\ShopContentRemind;
use App\Http\Controllers\H5\BaseController;
use Illuminate\Support\Facades\Redis;

class PushRemindController extends BaseController
{
    /**
     * 推送项开启状态
    */
    public function display()
    {
        $shopId = $this->shop['id'];
        $types = ShopRemindStatus::where('shop_id',$shopId)->value('types');
        $data = [];
        if($types){
            $data = unserialize($types);
        }
        return $this->output($data);
    }

    /**
     * 开启推送
    */
    public function open()
    {
        $this->validateWithAttribute([
            'content_id' => 'required',
            'content_type'  => 'required|in:live,course,column'
        ],[
            'content_id' => '内容id',
            'content_type'  => '内容类型',
        ]);
        $shopId = $this->shop['id'];
        $source = $this->member['source'];
        $openid = $this->member['openid'];
        $contentId = request('content_id');
        $contentType = request('content_type');
        $formId = request('form_id');

        $types = ShopRemindStatus::where('shop_id',$shopId)->value('types');
        if(!$types){
            $this->error('push_not_open');
        }
        $types = unserialize($types);
        if(!$types[$contentType]){
            $this->error('push_not_open');
        }
        $obj = ShopContentRemind::where([
            'shop_id'=>$shopId,
            'source'=>$source,
            'content_id'=>$contentId,
            'content_type'=>$contentType,
            'openid'=>$openid
        ])->first();
        if(!$obj){
            $data = [
                'shop_id'=>$shopId,
                'source' =>$source,
                'openid' => $openid,
                'content_id' => $contentId,
                'content_type' => $contentType,
                'push_status' => 1,
                'create_time' => time()
            ];
            ShopContentRemind::insert($data);
        }
        isset($formId) && Redis::sadd('remind:form_id:'.$openid,$formId);
        isset($formId) && Redis::expire('remind:form_id:'.$openid,7*24*3600);
        return $this->output(['success'=>1]);
    }

    /**
     * 关闭推送
    */
    public function close()
    {
        $this->validateWithAttribute([
            'content_id' => 'required',
            'content_type'  => 'required|in:live,course,column'
        ],[
            'content_id' => '内容id',
            'content_type'  => '内容类型',
        ]);

        $shopId = $this->shop['id'];
        $source = $this->member['source'];
        $openid = $this->member['openid'];

        $contentId = request('content_id');
        $contentType = request('content_type');
        ShopContentRemind::where([
            'shop_id'=>$shopId,
            'source'=>$source,
            'content_id'=>$contentId,
            'content_type'=>$contentType,
            'openid'=>$openid
        ])->delete();
        return $this->output(['success'=>1]);
    }

    public function getAuthUrl(){
        $this->validateWithAttribute(['redirect_url'=>'required'],['redirect_url'=>'回调地址']);
        $scene = date('is',time())+mt_rand(1000,4000);
        $url = SUBSCRIBE_MSG
            . '?action=get_confirm'
            . '&appid=' . config('wechat.app_id')
            . '&scene=' . $scene
            . '&template_id=' . env('TEMPLATE_ID','oI1f5jXQrdh4pp8q8I-49C_PvGb3_yLdjs-eUB8VF3c')
            . '&redirect_url=' . urlencode(request('redirect_url'))
            . '#wechat_redirect';
        Redis::sadd('remind:scene:'.$this->member['openid'],$scene);
        return $this->output(['url' => $url]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|void
     * 同意授权后开通提醒
     */
    public function callBack(){
        $this->validateWithAttribute(['action'=>'required','scene'=>'required'],['action'=>'授权操作','scene'=>'场景值']);
        $action = request('action');
        if($action == 'confirm'){
            $this->validateWithAttribute(['openid'=>'required'],['openid'=>'用户唯一标识']);
            $data = [
                'shop_id'=>$this->shop['id'],
                'source' =>'wechat',
                'openid' => request('openid'),
                'content_id' => request('content_id'),
                'content_type' => request('content_type'),
                'push_status' => 1,
                'scene' => request('scene'),
                'create_time' => time()
            ];
            ShopContentRemind::insert($data);
            return $this->output(['success'=>1]);
        }else{
            return $this->error('auth_cancel');
        }
    }

    public function saveFormId(){
        $this->validateWithAttribute(['form_id'=>'required'],['form_id'=>'form_id']);
        $key = 'remind:form_id:'.$this->member['openid'];
        Redis::sadd($key,request('form_id'));
        Redis::expire($key,7*24*3600);
        return $this->output(['success'=>1]);
    }
}