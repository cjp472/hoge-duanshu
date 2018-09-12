<?php
namespace App\Http\Controllers\Admin\Content;

use GuzzleHttp\Client;
use App\Models\Alive;
use App\Models\Content;
use App\Events\CurlLogsEvent;
use App\Models\ShopTemplateId;
use App\Models\ShopRemindStatus;
use App\Models\ShopContentRemind;
use App\Events\PushTemplateEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Http\Controllers\Admin\OpenPlatform\CoreTrait;
use Illuminate\Support\Facades\Cache;

class PushRemindController extends BaseController
{
    use CoreTrait;
    protected $type = 'applet';
    const WX_ADD_TEMPLATE = 'https://api.weixin.qq.com/cgi-bin/wxopen/template/add';

    //课程数据模板1
//    private $course_template_data = [
//        "id" => "AT0900",
//        "keyword_id_list" => [4,7]
//    ];
    //课程数据模板2
    private $course_template_data = [
        "id" => "AT0056",
        "keyword_id_list" => [1,15]
    ];
    /**
     * 推送列表
    */
    public function displayPush()
    {
        $shopId = $this->shop['id'];
        $types = ShopRemindStatus::where('shop_id',$shopId)->value('types');
        $data = [];
        if($types){
            $data = unserialize($types);
        }
        return $this->output($data ? : new \stdClass());
    }

    /**
     * 开启推送
    */
    public function openPush()
    {
        $this->validateWithAttribute([
            'types'=>'required|array'
        ],[
            'types'=>'推送项'
        ]);
        $shopId = $this->shop['id'];
        $obj = ShopRemindStatus::where('shop_id',$shopId)->first();
        $types = request('types');
        $templateId = ShopTemplateId::where('shop_id',$shopId)->value('course_template_id');
        if($types['column']==true || $types['course']==true){
            if(!$templateId){
                $this->applyTemplateId();
            }
        }
        if($obj){
            $obj->types = serialize($types);
            $obj->save();
        }else{
            ShopRemindStatus::insert([
                'shop_id' => $shopId,
                'types' => serialize($types),
                'create_time' => time()
            ]);
        }
        return $this->output(['success'=>1]);
    }

    protected function applyTemplateId()
    {
        $shopId = $this->shop['id'];
        $data = ['shop_id'=>$shopId];
        $data['course_template_id'] = $this->setRequest($this->course_template_data);
        ShopTemplateId::insert($data);
    }

    public function getAccessToken()
    {
        $shopId = $this->shop['id'];
        $authorizationData = $this->getAuthorizerAccessToken();
        $accessToken = $authorizationData['authorizer_access_token'];
        Cache::put('push:applet:'.$shopId.':access_token',$accessToken,110);
        return $accessToken;
    }

    protected function setRequest($data)
    {
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'  => json_encode($data),
        ]);
        $url = self::WX_ADD_TEMPLATE.'?access_token='.$this->getAccessToken();
        $response = $client->request('POST',$url);
        $response = json_decode($response->getBody()->getContents());
        if($response->errcode == 0){
            return $response->template_id;
        }
        event(new CurlLogsEvent($response,$client,$url));
        $this->error($response->errmsg);
    }

    public function test()
    {
        $obj = ShopContentRemind::select(
            'live.start_time',
            'shop_content_remind_record.id',
            'shop_content_remind_record.content_id',
            'shop_content_remind_record.openid',
            'shop_content_remind_record.scene',
            'shop_content_remind_record.content_type',
            'shop_content_remind_record.source'
        )->leftJoin('live','shop_content_remind_record.content_id','=','live.content_id')
            ->where(['push_status'=>0,'content_type'=>'live','source'=>'wechat'])
            ->get();
        $time = time();
        if(!$obj->isEmpty()){
            foreach($obj as $value){
                if($time+300 >= $value->start_time){
                    event(new PushTemplateEvent($value));
                }
            }
        }
    }

    /**s
     * 课程或栏目推送
    */
    public function pushContents()
    {
        $this->validateWithAttribute([
            'content_id'=>'required',
            'content_type'=>'required|in:course,column'
        ],[
            'content_id'=>'内容id',
            'content_type'=>'类型'
        ]);

        $shopId = $this->shop['id'];

        $types = ShopRemindStatus::where('shop_id',$shopId)->value('types');
        if(!$types){
            $this->error('push_not_open');
        }
        $types = unserialize($types);
        if(!$types[request('content_type')]){
            $this->error('push_not_open');
        }

        $obj = ShopContentRemind::select('id','shop_id','source','openid','content_id','content_type','form_id','scene')->where([
            'shop_id'=>$shopId,
            'content_id'=>request('content_id'),
            'content_type'=>request('content_type')
        ])->get();

        if(!$obj->isEmpty()){
            foreach($obj as $value){
                $accessToken = '';
                if('applet' == $value->source){
                    $accessToken = $this->getAccessToken();
                }
                event(new PushTemplateEvent($value,$accessToken));
            }
        }
        return $this->output(['success'=>1]);
    }
}
