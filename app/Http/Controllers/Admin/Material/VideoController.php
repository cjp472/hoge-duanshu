<?php
/**
 * 视频上传
 */

namespace App\Http\Controllers\Admin\Material;

use App\Events\ErrorHandle;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Material;
use App\Models\VideoClass;
use Illuminate\Http\Request;
use App\Models\Content;
use App\Models\Video;
use App\Models\Videos;
use Illuminate\Support\Facades\Redis;

class VideoController extends BaseController
{
    public function signature(Request $request)
    {
        $secretKey = config('qcloud.secret_key');
        $srcStr = $request->args;
        $signStr = base64_encode(hash_hmac('sha1', $srcStr, $secretKey, true));
        return $this->output(['sign'=>$signStr]);
    }

    public function newSignature(){

        $currentTime = time();
        $expireTime = $currentTime + config('qcloud.cos.signature_expire_time');
        $secret_key = config('qcloud.secret_key');
        $arg_list = [
            "secretId"         => config('qcloud.secret_id'),
            "currentTimeStamp" => $currentTime,
            "expireTime"       => $expireTime,
            "random"           => time(),
            "classId"          => $this->getVideoClassId(),
            "isTranscode"      => 1,
        ] ;
        $key_list = http_build_query($arg_list);
        $signature = base64_encode(hash_hmac('SHA1', $key_list, $secret_key, true).$key_list);
        return $this->output(['sign' => stripslashes($signature)]);
    }

    private function createVideoClass()
    {
        $param = [
            'className' => $this->shop['id'],
            'Action'    => 'CreateClass'
        ];

        $param['parentId'] = config('qcloud.classId');

        $result = \QcloudApi_Common_Request::send($param, config('qcloud.secret_id'), config('qcloud.secret_key'), 'GET',
            config('qcloud.delete.host'), config('qcloud.delete.path'));
        if(!$result){
            $this->errorWithText('qcloud_error','视频请求错误');
        }
        if($result['code'] == 4000){
            $vc = VideoClass::where('shop_id',$this->shop['id'])
                ->first();
            if($vc && !$vc->parent_id ){
                return $vc->class_id;
            }
        }elseif($result['code']){
            $this->errorWithText('create_class_error',$result['message']);
        }else{
            if($result['newClassId']){
                return $result['newClassId'];
            }
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 同步视频分类id
     */
    public function syncVideoClass(){
        $shop_id = Material::where(['type'=>'video'])->distinct()->pluck('shop_id')->toArray();
        if($shop_id){
            foreach ($shop_id as $id){
                if(!VideoClass::where('shop_id',$id)->value('class_id')){
                    $param = [
                        'className' => $id,
                        'Action'    => 'CreateClass'
                    ];
                    $param['parentId'] = config('qcloud.classId');
                    $result = \QcloudApi_Common_Request::send($param, config('qcloud.secret_id'), config('qcloud.secret_key'), 'GET',
                        config('qcloud.delete.host'), config('qcloud.delete.path'));
                    if(!$result){
                        $this->errorWithText('qcloud_error','视频分类同步错误');
                    }
                    if($result['code']){
                        $this->errorWithText('create_class_error',$result['message']);
                    }else{
                        if($result['newClassId']){
                            $vc = new VideoClass;
                            $vc->shop_id = $id;
                            $vc->class_id = $result['newClassId'];
                            $vc->save();
                        }
                        return $this->output(['success'=>1]);
                    }
                }
                return $this->output(['success'=>1]);
            }
        }
    }


    private function saveShopClassId($id)
    {
        $vc = new VideoClass;
        $vc->shop_id = $this->shop['id'];
        $vc->class_id = $id;
        $vc->parent_id = config('qcloud.classId');
        $vc->save();
    }

    private function getVideoClassId()
    {
        $parentId = config('qcloud.classId');
        $key = 'newvideo:class:'.$parentId.':'.$this->shop['id'];
        if(Redis::exists($key)){
            return Redis::get($key);
        }elseif( $id = VideoClass::where('shop_id',$this->shop['id'])->where('parent_id',$parentId)->value('class_id')){
            Redis::set($key,$id);
            return $id;
        }else{
            $id = $this->createVideoClass();
            $this->saveShopClassId($id);
            Redis::set($key,$id);
            return $id;
        }
    }

    public function callback(Request $request)
    {
        if($request->task == 'file_upload'){
            $videos = new Videos();
            $videos->status = 0;
            $videos->file_id = $request->data['file_id'];
            $videos->file_name = $request->data['image_video']['videoUrls'][0]['filename'];
            $videos->url = $request->data['image_video']['videoUrls'][0]['url'];
            $videos->save();
        }elseif ($request->task == 'transcode'){
            $videos = Videos::where('file_id',$request->data['file_id'])->firstOrFail();
            $videos->status = $request->data['ret'] > 0 ? 2 : 1;
            $videos->play_set = $request->data['image_video'] ? serialize($request->data['image_video']): '';
            $videos->cover_url = isset($request->data['image_video']['imgUrls'][0]['url']) ? $request->data['image_video']['imgUrls'][0]['url'] : '';
            $videos->save();
        }elseif ($request->eventType == 'NewFileUpload'){
            $videos = new Videos();
            $videos->status = 0;
            $videos->file_id = $request->data['fileId'];
            $videos->url = $request->data['fileUrl'];
            $videos->save();
        }elseif ($request->eventType == 'TranscodeComplete'){
            $videos = Videos::where('file_id',$request->data['fileId'])->first();
            if(!$videos){
                $videos = new Videos();
            }
            $videos->file_id = $request->data['fileId'];
            $videos->status = $request->data['status'] == 0 ? 1 : 2;
            $videos->play_set = isset($request->data['playSet']) ? serialize($request->data['playSet']): '';
            $videos->file_name = isset($request->data['fileName']) ? $request->data['fileName'] : '';
            $videos->cover_url = isset($request->data['coverUrl']) ? $request->data['coverUrl'] : '';
            $videos->save();
        } elseif ($request->eventType == 'ProcedureStateChanged' && $request->data['errCode'] == 0 && $request->data['status'] == 'FINISH'){
            // 任务流状态变更
            $data = $request->data;
            // 任务队列
            $tasks = $data['processTaskList'];
            if (!$tasks || count($tasks) == 0) {
                return $this->output(['success'=>1]);
            }
            $videos = Videos::where('file_id',$request->data['fileId'])->first();
            if(!$videos){
                $videos = new Videos();
            }
            $play_set = $videos->play_set? unserialize($videos->play_set):[];
            foreach ($tasks as $task) {
                // 转码任务 并且转码成功
                if($task['taskType'] == 'Transcode' && $task['errCode'] == 0 && $task['status'] == 'SUCCESS') {
                    $input = $task['input'];
                    $output = $task['output'];
                    $play_set_item = [
                        'url' => $output['url'],
                        'definition' => $input['definition'],
                        'vbitrate' =>$output['bitrate'],
                        'vheight' =>$output['height'],
                        'vwidth' =>$output['width'],
                    ];
                    array_push($play_set, $play_set_item);
                }
            }
            $videos->file_id = $request->data['fileId'];
            $videos->status = 1;
            $videos->play_set = serialize($play_set);
            $videos->save();
        }
        if( isset($videos) &&  $videos->file_id){
            $param = [
                'Action'    => 'GetVideoInfo',
                'fileId'    => $videos->file_id,
                'infoFilter.0'=> 'basicInfo',
                'infoFilter.1'=> 'metaData',
            ];
            $result = \QcloudApi_Common_Request::send($param, config('qcloud.secret_id'), config('qcloud.secret_key'), 'GET',
                config('qcloud.vod.host'), config('qcloud.vod.path'));
            if( 0 == $result['code'] ) {
                $videos->duration = isset($result['basicInfo']) ? $result['basicInfo']['duration'] : '00:00';
                $videos->ratio = isset($result['metaData']) ? serialize([
                    'width'=>$result['metaData']['width'],
                    'height'=>$result['metaData']['height'] ,
                    'rotate'=>$result['metaData']['rotate']]
                ) : '';
                $videos->save();
            }
        }
        return $this->output(['success'=>1]);
    }
}