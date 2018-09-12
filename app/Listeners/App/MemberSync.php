<?php

namespace App\Listeners\App;


use App\Events\AppEvent\AppMemberEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\ShopApp;
use App\Models\SyncMember;
use Illuminate\Contracts\Queue\ShouldQueue;


class MemberSync implements ShouldQueue
{

    /**
     * 队列名称
     * @var string
     */
    public $queue = DEFAULT_QUEUE;

    /**
     * @param AppMemberEvent $event
     */
    public function handle(AppMemberEvent $event)
    {

        $shop_app = ShopApp::where('shop_id',$event->data['shop_id'])->first();

        if($shop_app && $shop_app->appkey && $shop_app->appsecret){
            $param = $this->validateMember($event->data);
            if($param){
                $client = hg_hash_sha1($param,$shop_app->appkey,$shop_app->appsecret,time());
                try{
                    $response = $client->request('POST',str_replace('{app_id}',$shop_app->appkey,config('define.dingdone.api.member')));
                }catch (\Exception $exception){
                    event(new ErrorHandle($exception,'app'));
                    return true;
                }
                event(new CurlLogsEvent($response,$client,str_replace('{app_id}',$shop_app->appkey,config('define.dingdone.api.member'))));
                $result = json_decode($response->getBody()->getContents(), 1);
                //记录curl日志
                if ($result['error_code'] == 0) {
                    $this->syncMember($param,$event->data['shop_id']);
                }
            }
        }
    }

    /**
     * 参数处理
     * @param $data
     * @return mixed
     */
    private function validateMember($data){
        $member['user_list'][0]['username'] = $data['id'];
        $member['user_list'][0]['uid'] = $data['id'];
        $member['user_list'][0]['nick_name'] = $data['nick_name'] ? : 'ds_'.$data['id'];
        isset($data['sex']) ? ($member['user_list'][0]['gender'] = $data['sex'] == 1 ? 1 : ($data['sex'] == 2 ? 0 : 2)) : '';
        isset($data['mobile']) && $member['user_list'][0]['mobile'] = $data['mobile'];
        isset($data['email']) && $member['user_list'][0]['email'] = $data['email'];
        isset($data['qq']) && $member['user_list'][0]['extra_info'] = ['qq'=>$data['qq']?:''];
        return $member;
    }

    /**
     * 同步会员记录
     * @param $member
     * @param $shop_id
     * @return bool
     */
    private function syncMember($member,$shop_id)
    {
        if($member) {
            $uid = SyncMember::whereIn('uid',array_column($member['user_list'],'id'))->pluck('uid')->toArray();
            $data = [];
            foreach ($member['user_list'] as $key => $item) {
                if(!in_array($item['uid'],$uid)) {
                    $data[] = [
                        'uid' => $item['uid'],
                        'shop_id' => $shop_id
                    ];
                }
            }
            $data && SyncMember::insert($data);
        }
        return true;
    }



}
