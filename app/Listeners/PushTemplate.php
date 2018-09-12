<?php
namespace App\Listeners;

use GuzzleHttp\Client;
use App\Models\Content;
use App\Models\Course;
use App\Models\Column;
use App\Events\CurlLogsEvent;
use App\Models\ShopContentRemind;
use App\Events\PushTemplateEvent;
use Illuminate\Support\Facades\Cache;
use App\Models\ShopTemplateId;
use Illuminate\Support\Facades\Redis;

class PushTemplate
{
    protected $wxUrl = 'https://api.weixin.qq.com/cgi-bin/message/template/subscribe?access_token=';
    protected $appletUrl = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=';

    public function handle(PushTemplateEvent $event)
    {
        $data = $event->content;$content_title=$course_type='';
        if('live' == $data->content_type || (in_array($data->content_type,['course','column']) && 'wechat' == $data->source)){
            $accessToken = $this->returnAccessToen();
//            $accessToken = '10_5p0SEQe82PJhvzBTo_deq9MuG_OvF-T4aoLDlQSkeEYh6b6cmn0cg_MoJvPzmpOjpQF-6j-dUlEBW2gboxaWIjcYEdCHsZdFTPwb_jpNjcnYNooX-VizE48h95RjbPmvp_bbOnIW_thTZCAuFNAdCAARZM';
            $url = $this->wxUrl.$accessToken;
            $scene = Redis::srandmember('remind:scene:'.$data->openid);
            $info = [
                'touser' => $data->openid,
                'template_id' => env('TEMPLATE_ID','oI1f5jXQrdh4pp8q8I-49C_PvGb3_yLdjs-eUB8VF3c'),
                'url' => H5_DOMAIN.'/'.$data->shop_id.'/#/brief/live/'.$data->content_id,
                'scene' => $scene,
                'title' => '直播/课程进度提醒'
            ];
            Redis::srem('remind:scene:'.$data->openid,$scene);
            if('live' == $data->content_type){
                $title = Content::where(['hashid'=>$data->content_id,'shop_id'=>$data->shop_id])->value('title');
                $date = date('Y-m-d H:i:s',$data->start_time);
                $info['data']['content']['value'] = "您好！您订阅的直播即将开播了\n内容:$title\n开播时间:$date\n可在直播页面关闭提醒功能";
            }else{
                if('course' == $data->content_type){
                    $title = Course::where(['hashid'=>$data->content_id,'shop_id'=>$data->shop_id])->value('title');
                }elseif('column' == $data->content_type){
                    $title = Column::where(['hashid'=>$data->content_id,'shop_id'=>$data->shop_id])->value('title');
                }
                $info['data']['content']['value'] = "您好！您订阅的课程有更新了\n课程名称:$title\n课程状态:已更新\n课程进度:未学习\n可在课程页面关闭提醒功能";
            }
        }elseif(in_array($data->content_type,['course','column']) && 'applet' == $data->source){
            $accessToken = $event->access_token;
            $url = $this->appletUrl.$accessToken;
            $templateId = ShopTemplateId::where('shop_id',$data->shop_id)->value('course_template_id');
            $formId = Redis::srandmember('remind:form_id:'.$data->openid);
            if('course' == $data->content_type){
                $content_title = Course::where(['hashid'=>$data->content_id,'shop_id'=>$data->shop_id])->value('title');
                $course_type = Course::where(['hashid'=>$data->content_id,'shop_id'=>$data->shop_id])->value('course_type');
            }elseif('column' == $data->content_type){
                $content_title = Column::where(['hashid'=>$data->content_id,'shop_id'=>$data->shop_id])->value('title');
            }
            $info = [
                "touser" => $data->openid,
                "template_id" => $templateId,
                'page' => $data->content_type=='column'?'pages/column/column?cid='.$data->content_id:'pages/bricourse/bricourse?cid='.$data->content_id.'&type'.$course_type,
                'form_id' => $formId,
                'data' => [
                    "keyword1"=>[
                        'value'=>$content_title,
                    ],
                    "keyword2"=>[
                        'value'=>$data->course_title,
                    ]
                ]
            ];
            Redis::srem('remind:form_id:'.$data->openid,$formId);
        }
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'  => json_encode($info),
        ]);
        $response = $client->request('POST',$url);
        $response = json_decode($response->getBody()->getContents());
        if(0 == $response->errcode){
            if('wechat' == $data->source && in_array($data->content_type,['course','column'])){
                ShopContentRemind::where('id',$data->id)->delete();
            }
        }
    }

    protected function returnAccessToen()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.config('wechat.app_id').'&secret='.config('wechat.secret');
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
        $response = $client->request('GET',$url);
        $response = json_decode($response->getBody()->getContents());
        $accessToken = $response->access_token;
        Cache::put('push:wx:access_token',$accessToken,110);
        return $accessToken;
    }
}
