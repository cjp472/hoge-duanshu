<?php

namespace App\Http\Controllers\Admin\Material;

use App\Events\ErrorHandle;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Alive;
use App\Models\Audio;
use App\Models\Banner;
use App\Models\Community;
use App\Models\CommunityNote;
use App\Models\Manage\Content;
use App\Models\Material;
use App\Models\ShopDisable;
use App\Models\ShopFlow;
use App\Models\Video;
use App\Models\Videos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use qcloudcos\Cosapi;
use QcloudApi_Common_Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use GuzzleHttp\Client;

class MaterialController extends BaseController
{
    /**
     * 列表
     * @return mixed
     */
    public function lists(){
        $this->validateWithAttribute([
            'type'   => 'required|alpha_dash|in:text,image,audio,video',
            'sign' => 'required|alpha_dash|in:courseware,manage',
            'count'  => 'numeric'],
            ['type'=>'类型','sign'=>'类别','count'=>'每页条数']
        );
        $where = [
            'shop_id' => $this->shop['id'],
//            'user_id' => $this->user['id'],
            'type'    => request('type'),
            'sign'    => request('sign'),
            'is_del'    => 0,
        ];
        $count = request('count') ? : 10;
        $result =  Material::where($where)->orderBy('is_top','desc')->orderBy('updated_at','desc')->select('id','title','content','created_at','type','is_display','is_top')->paginate($count);
        foreach ($result as $item){
            $item->content = $item->content ? unserialize($item->content) : [];
            if(request('type') == 'video'){
                $content = $item->content;
                $content['cover_url'] ? $content['cover_url'] = hg_unserialize_image_link($content['cover_url'],1) : ($content['cover_url'] = Videos::where('file_id', $content['file_id'])->value('cover_url') ? hg_unserialize_image_link(Videos::where('file_id', $content['file_id'])->value('cover_url'),1): hg_unserialize_image_link(config('define.default_pic')));
                $item->content = $content;
            }elseif (request('type') == 'image'){
                $content = $item->content;
                $content['url'] = hg_unserialize_image_link(isset($content['url'])?$content['url']:'');
                $item->title = $item->title?:(isset($content['file_name'])?$content['file_name']:'');
                $item->content = $content;
            }
        }
        return $this->output($this->listToPage($result));
    }


    /**
     * 新增
     * @return mixed
     */
    public function saveMaterial(){
        $this->checkShopClosed();
        $this->validateWithAttribute([
            'type' => 'required|alpha_dash|in:text,image,audio,video',
            'sign' => 'required|alpha_dash|in:courseware,manage'],
            ['type'=>'类型','sign'=>'类别']
        );
        $result = $this->sameAdd();
        return $this->output($result);
    }

    private function checkShopClosed(){
//        $close_shops = Redis::smembers('close:shop');
//        if(in_array($this->shop['id'],$close_shops)){ //店铺打烊
//            return response([
//                'error'     => 'shop-closed',
//                'message'   => trans('validation.shop-closed'),
//            ]);
//        }
        if(ShopDisable::isShopDisable($this->shop['id'])){
            return response([
                'error'     => 'shop-closed',
                'message'   => trans('validation.shop-closed'),
            ]);
        }
    }

    /**
     * 新增公用方法
     */
    private function sameAdd(){
        if(request('type') == 'text' || (request('sign') == 'courseware' && request('type') == 'audio')){ //单个添加
            $material = new Material();
            $material->shop_id = $this->shop['id'];
            $material->user_id = $this->user['id'];
            $material->union_id  = '';
            $material->type = request('type');
            $material->sign = request('sign');
            $content = request('content');
            $material->content = serialize($content);
            $material->title = trim(request('title'));
            $material->is_top = 1;
            $material->created_at = date('Y-m-d H:i:s',time());
            $material->updated_at = date('Y-m-d H:i:s',time());
            $material->save();
            $material->content = $material->content?unserialize($material->content):[];
            return $material;
        }else{  //图片、音频，视频批量添加
            return  $this->batchAudioVideo();
        }
    }

    //批量添加音频视频
    private function batchAudioVideo(){
        if(request('content')){
//            $numberical = 0;
            foreach (request('content') as $item){
                $content = $item['info'];
                if(request('type') == 'image'){
                    $content['url'] = hg_explore_image_link($item['info']['url']);
                }
                if(request('type') == 'video') {
                    $video = Videos::where('file_id', $item['info']['file_id'])->first();
                    $content['cover_url'] = isset($video->cover_url) ? hg_explore_image_link($video->cover_url,1) : '';
                    $content['duration'] = hg_sec_to_time(isset($video->duration) ? $video->duration : 0)?:'00:00';
                }
                if(request('type') == 'audio') {
                    $content['duration'] = hg_get_file_time($item['info']['url'],1);
                }
                $material[] = [
                    'shop_id'     => $this->shop['id'],
                    'user_id'     => $this->user['id'],
                    'union_id'    => '',
                    'type'        => request('type'),
                    'sign'        => request('sign'),
                    'is_top'      => 1,
                    'title'       => isset($item['title']) ? $item['title'] : '',
                    'created_at'   => date('Y-m-d H:i:s',time()),
                    'updated_at'   => date('Y-m-d H:i:s',time()),
                    'content'     =>serialize($content)
                ];
            }
            Material::insert($material);
//            $this->formatShopFlow($numberical);
        }
        return ['success'=>1];
    }

    private function formatShopFlow($numberical){
        $start_time = date('Y-m-01 00:00:00',time());
        $time = date_add(date_create($start_time),date_interval_create_from_date_string('1 months'));
        $start_time = strtotime(date('Y-m-01 00:00:00',time()));
        $end_time = strtotime(date_format($time,'Y-m-d H:i:s'));
        $storage = ShopFlow::where(['remark'=>'storage','shop_id'=>$this->shop['id'],'flow_type'=>0])->whereBetween('time',[$start_time,$end_time])->sum('numberical');
        $data = [
            'shop_id'   =>$this->shop['id'],
            'numberical' => $numberical,      //单位kb
            'remark'    => 'storage',
            'time'      => time(),
            'unit_price'=> 0,
            'price'     => 0,
            'flow_type' => 0,
        ];
        if(DEFAULT_STORAGE - $storage < 0 ){
            $data['unit_price'] = DEFAULT_STORAGE_UNIT_PRICE;
            $data['price'] = DEFAULT_STORAGE_UNIT_PRICE*($numberical/1048576);
            $data['flow_type'] = 1;
        }
        ShopFlow::insert($data);
    }



    /**
     * 更新
     * @return mixed
     */
    public function updateMaterial(){
        $this->validateWithAttribute([
            'id'   => 'required|numeric',
            'type' => 'required|alpha_dash|in:text,image,audio,video',
            'sign' => 'required|alpha_dash|in:courseware,manage'],
            ['id'=>'素材id','type'=>'类型','sign'=>'类别']
        );
        $material = Material::where([
//            'user_id' => $this->user['id'],
            'shop_id' => $this->shop['id'],
            'sign' => request('sign')
        ])->findOrFail(request('id'));
        $material->title = trim(request('title'));
        $content = request('content');
        if(request('type') == 'video') {
            $cover_url = Videos::where('file_id', request('content.file_id'))->value('cover_url') ? : '';
            $content['cover_url'] = $cover_url ? hg_explore_image_link($cover_url,1) : '';
        }elseif(request('type') == 'image'){
            $content['url'] = hg_explore_image_link($content['url']);
        }
        $material->content = serialize($content);
        $material->save();
        $material->content = $material->content?unserialize($material->content):[];
        return $material;
    }


    /**
     * 素材详情
     * @return mixed
     */
    public function detail(){
        $this->validateWithAttribute([
            'id' => 'required|numeric',
            'sign' => 'required|alpha_dash|in:courseware,manage'],
            ['id'=>'id','sign'=>'类别']
        );
        $data = Material::where([
//            'user_id' => $this->user['id'],
            'shop_id' => $this->shop['id'],
            'sign' => request('sign')
            ])->findOrFail(request('id'),['id','title','type','created_at','updated_at','content','is_display','is_top']);
        $data->content = $data->content ? unserialize($data->content) : [];
        return $this->output($data);
    }


    /**
     * 删除数据
     * @return mixed
     */
    public function deleteMaterial(){
        $this->validateWithAttribute([
            'id'    => 'required',
            'sign' => 'required|alpha_dash|in:courseware,manage'],
            ['id'=>'id','sign'=>'类别']
        );
        $params = explode(',',request('id'));
//        $info = Material::where([
////            'user_id' => $this->user['id'],
//            'shop_id' => $this->shop['id'],
//            'sign' => request('sign')
//        ])->whereIn('id',$params)->get();
//        if(!$info->isEmpty()){
//            foreach($info as $result){
//                $type = $result->type;
//                if($type == 'video'){
//                    $this->videoIsUsed($result->content); //判断视频是否被使用
//                    $this->deleteCloudVideo($result->content);
//                }elseif($type == 'audio'){
//                    $this->audioIsUsed($result->content);  //判断音频是否被使用
//                }elseif($type == 'image'){
//                  $this->imageIsUsed($result->content);
//                }
//                $result->delete();  //删除本地素材
//            }
//        }
        Material::where([
            'shop_id' => $this->shop['id'],
            'sign' => request('sign')
        ])->whereIn('id',$params)->update(['is_del' => 1]);
        return $this->output(['success'=>1]);
    }


    /**
     * 判断图片是否被使用
     * @param $data
     */
    private function imageIsUsed($data){
        $param = unserialize($data);
        $content = hg_unserialize_image_link(isset($param['url'])?$param['url']:'');
        $url = $content['host'].$content['file'].$content['query'];
        $urls = hg_explore_image_link($url);
        $content = Content::where('indexpic',$url)->orWhere('indexpic',$urls)->first();
        $video = Video::where('patch',$url)->orWhere('patch',$urls)->first();
        $live = Alive::where('live_indexpic',$url)->orWhere('live_indexpic',$urls)->first();
        $banner = Banner::where('indexpic',$url)->orWhere('indexpic',$urls)->first();
        $community = Community::where('indexpic',$url)->orWhere('indexpic',$urls)->first();
        $community_note = CommunityNote::where('indexpic',$url)->orWhere('indexpic',$urls)->first();
        if($content || $video || $live || $banner || $community || $community_note){
            $this->error('image_used');
        }
    }

    /**
     * 判断音频是否被使用
     * @param $data
     */
    private function audioIsUsed($data){
        $content = unserialize($data);
        $url = $content['url'];
        $audio = Audio::where('url',$url)->first();
        if($audio){  //音频存在
            $this->error('audio_used');
        }
    }

    /**
     * 判断视频是否被使用
     * @param $data
     * @return bool
     */
    private function videoIsUsed($data){
        $content = unserialize($data);
        $file_id = $content['file_id'];
        $isVideo = Video::where('file_id',$file_id)->first();
        $isLive = Alive::where('file_id',$file_id)->first();
        if($isVideo || $isLive){ //视频已经被使用
            $this->error('video_used');
        }
    }


    /**
     * 删除视频
     * @param $data
     * @return bool
     */
    private function deleteCloudVideo($data){
        $content = unserialize($data);
        $file_id = $content['file_id'];
        $param = ['fileId' => $file_id,'priority' => 0,'Action'=>'DeleteVodFile'];
        try{
            $result = QcloudApi_Common_Request::send($param, config('qcloud.secret_id'), config('qcloud.secret_key'), 'GET',
                config('qcloud.delete.host'), config('qcloud.delete.path'));
        }catch (\Exception $e){
            event(new ErrorHandle($e,'tencent_cloud'));
        }
        if ($result['code'] != 0 && $result['code'] != 4000) {
            $this->errorWithText($result['code'],$result['message']);
        }
    }

    /**
     * 置顶
     */
    public function topMaterial(){
        $this->validateWithAttribute([
            'id'    => 'required|numeric',
            'is_top' => 'required|numeric|in:0,1',
            'sign' => 'required|alpha_dash|in:courseware,manage'],
            ['id'=>'id','is_top'=>'置顶参数','sign'=>'类别']
        );
        $material = Material::where([
//            'user_id' => $this->user['id'],
            'shop_id' => $this->shop['id'],
            'sign' => request('sign')
        ])->findOrFail(request('id'));
        $material->is_top = request('is_top');
        $material->updated_at = date('Y-m-d H:i:s',time());
        $material->save();
        $material->content = $material->content?unserialize($material->content):[];
        return $material;
    }

    /**
     * 显示隐藏
     * 0-隐藏，1-显示
     * @return mixed
     */
    public function setStatus(){
        $this->validateWithAttribute([
            'id'      => 'required',
            'is_display'  => 'required|numeric|in:0,1',
            'sign' => 'required|alpha_dash|in:courseware,manage'],
            ['id'=>'id','is_display'=>'显示隐藏参数','sign'=>'类别']
        );
        $params = explode(',',request('id'));
        if(is_array($params)){
            foreach($params as $param){
                $material = Material::where([
//                    'user_id' => $this->user['id'],
                    'shop_id' => $this->shop['id'],'sign' => request('sign')])->findOrFail($param);
                $material->is_display = request('is_display');
                $material->save();
                $material->content = $material->content?unserialize($material->content):[];
            }
        }
        return $this->output(['success'=>1]);
    }

    public function qrcodeMake(){
        $this->validateWithAttribute(['url'=>'required|url'],['url'=>'链接']);
        $path = resource_path('material/qrcode/'.md5(request('url')).'.png');
        QrCode::format('png')->size(100)->margin(0)->generate(request('url'), $path);
        $url = $this->uploadCos($path);
        return $this->output(['qrcode'=>$url]);
    }

    private function uploadCos($path){
        $cos_path = config('qcloud.folder').'/qrcode/'.md5(request('url')).'.png';
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$path,$cos_path);
        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        unlink($path);
        return $data['data']['source_url'];

    }

    /**
     * 上传素材url到cos
     * @return mixed
     */
    public function uploadUrl(){
        if(request()->toArray()){
            $client = new Client();
            foreach (request()->toArray() as $item){
                $material_info = pathinfo($item['url']);
                $file_name = md5($item['url']).(isset($material_info['extension']) ? '.'.$material_info['extension'] : '');
                $cos_path = config('qcloud.folder').'/'.$item['type'].'/'.$file_name;
//                $content = file_get_contents($item['url']);
                $content = $client->request('get',$item['url'])->getBody()->getContents();
                $upload_path = resource_path('material/admin/');
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0777, 1);
                }
                file_put_contents($upload_path . $file_name, $content);
                Cosapi::setRegion(config('qcloud.region'));
                $data = Cosapi::upload(config('qcloud.cos.bucket'),$upload_path.$file_name,$cos_path);
                $data['code'] && $this->errorWithText($data['code'],$data['message']);
                is_file($upload_path . $file_name) && unlink($upload_path . $file_name);
                $source_url = $item['type'] == 'image' ? hg_unserialize_image_link($data['data']['source_url']) : $data['data']['source_url'];
                $result[]=['hash' => $item['hash'],'url'=>$item['url'],'source_url'=>$source_url];
            }
            return $this->output($result);
        }

    }

    public function qiniuCallback(){
        Log::info(json_encode(request()->toArray()));
    }

}