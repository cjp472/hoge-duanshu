<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/4/12
 * Time: 09:14
 */

namespace App\Http\Controllers\H5\Material;


use App\Events\AudioTranscodeEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Http\Controllers\H5\BaseController;
use App\Models\AliveMessage;
use App\Models\Material;
use Doctrine\Common\Cache\PredisCache;
use EasyWeChat\Foundation\Application;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use qcloudcos\Cosapi;
use Qiniu\Auth;
use function Qiniu\base64_urlSafeEncode;
use Qiniu\Storage\UploadManager;

class MaterialController extends BaseController
{

    /**
     * 保存直播素材
     * @return mixed
     */
    public function saveMaterial(){

        $this->validateWith([
            'content_id'    => 'required|alpha_dash|size:12',
            'type'          => 'required|alpha_dash|in:text,image,audio',
        ]);
        $material = $this->createMaterial();
        return $this->output($material);
    }

    private function createMaterial(){
        $material = new Material();
        $material->setRawAttributes($this->setBaseData());
        $material->type = request('type');
        $content = request('content');
        if(request('type') == 'image'){
            $content['url'] = hg_explore_image_link($content['url']);
        }
        $material->content = serialize($content);
        $material->save();
        $content =$material->content ? unserialize($material->content) : [];
        if($material->type == 'image'){
            $content['url'] = $content['url'] ? hg_unserialize_image_link($content['url']) : '';
        }
        $material->content = $content ? $content:[];
        return $material;
    }

    /**
     * 设置基础数据
     * @return array
     */
    private function setBaseData(){
        $data = [
            'content_id'    => request('content_id'),
//            'user_id'       => request('user_id'),
            'shop_id'       => $this->shop['id'],
            'title'         => trim(request('title'))
        ];
        return $data;
    }

    /**
     * 获取素材列表
     * @return mixed
     */
    public function lists(){
        $this->validateWith([
            'type'  => 'required|alpha_dash|in:text,image,audio',
            'count' => 'numeric|max:10000',
        ]);
        $where = [
            'type'      => request('type'),
//            'user_id'   => request('user_id'),
            'shop_id'   => $this->shop['id'],
            'is_display'=> 1,
            'sign'      => 'courseware',
            'is_del'    => 0
        ];
        $count = request('count') ? : 20;
        $param = Material::where($where)->orderBy('is_top','desc')->orderBy('updated_at','desc')->paginate($count,['id','shop_id','title','content','type']);
        $material = $this->formatMaterial($param);
        return $this->output($this->listToPage($material));
    }

    private function formatMaterial($param){
        if($param){
            foreach($param as $v){
                $v->content = $v->content?unserialize($v->content):[];
                if (request('type') == 'image'){
                    $content = $v->content;
                    $content['url'] = $content['url'] ? hg_unserialize_image_link($content['url']) : '';
                    $v->content = $content;
                }
            }
            return $param;
        }
    }

    /**
     * 素材详情
     * @return mixed
     */
    public function detail(){
        $this->validateWith(['id' => 'required|numeric']);
        $where = [
            'user_id' => request('user_id'),
            'shop_id'   => $this->shop['id'],
        ];
        $material = Material::where($where)->findOrFail(request('id'),['id','title','type','content']);
        $content =$material->content ? unserialize($material->content) : [];
        if($material->type == 'image'){
            $content['url'] = $content['url'] ? hg_unserialize_image_link($content['url']) : '';
        }
        $material->content = $content ? $content:[];
        return $this->output($material);
    }

    /**
     * 删除素材
     * @return mixed
     */
    public function delete(){
        $this->validateWith([
            'id' => 'required|regex:/^[1-9]\d*(,\d*)*$/',
        ]);
        $id = explode(',',request('id'));
        Material::where('user_id',request('user_id'))->whereIn('id',$id)->delete();
        return $this->output(['success'=>1]);
    }

    /**
     * 更新素材信息
     * @return mixed
     */
    public function update()
    {
        $this->validateWith(['id' => 'required|numeric']);
        $material = Material::where('user_id',request('user_id'))->findOrFail(request('id'));
        $material->title = trim(request('title'));
        $content = request('content');
        if(request('type') == 'image'){
            $content['url'] = hg_explore_image_link($content['url']);
        }
        $material->content = serialize($content);
        $material->save();
        $content =$material->content ? unserialize($material->content) : [];
        if($material->type == 'image'){
            $content['url'] = $content['url'] ? hg_unserialize_image_link($content['url']) : '';
        }
        $material->content = $content ? $content:[];
        return $this->output($material);
    }

    /**
     * 上传图片,语音素材
     * @return mixed
     */
    public function uploadMaterial(){
        $this->validateWith([
            'server_id'=>'required|alpha_dash',
            'type'     => 'required|in:image,voice'
        ]);
        $url = $this->getMaterial();
        return $this->output($url);

    }

    private function getMaterial()
    {

        $app = new Application(config('wechat'));
        $app->cache = new PredisCache(app('redis')->connection()->client());
        try {
            $param = [
                'access_token' => $app->access_token->getToken(request('refresh_token') ? true : false),
                'media_id' => request('server_id'),
                'type' => request('type'),
            ];
            $url = config('wechat.api.media_get');
            $client = new Client([
//                'body'  => json_encode($param)
            ]);
            $material = $client->request('get', $url ,['query'=>$param]);
        } catch (\Exception $e) {
            event(new ErrorHandle($e,'tencent_cloud'));
            $this->error('upload_fail');
        }
        $material_content = $material->getBody()->getContents();
        event(new CurlLogsEvent(is_null(json_decode($material_content)) ? json_encode($material_content) : $material_content,$client,$url));
        if($content = json_decode($material_content,1)) {
            $content['errcode'] && $this->errorWithText($content['errcode'], $content['errmsg']);
        }else{
            $content = $material_content;
        }
        switch (request('type')) {
            case 'image':
            default :
                $file_name = md5(request('server_id')) . '.jpg';
                break;
            case 'voice':
                $file_name = md5(request('server_id')) . '.amr';
                break;
        }
        $upload_path = resource_path('material/h5/');
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, 1);
        }
        file_put_contents($upload_path . $file_name, $content);
        if(request('type') == 'voice') {
            return $this->uploadQiniu($upload_path, $file_name);
        }
        $url = $this->uploadCos($upload_path . $file_name, $file_name);
        return [
            'url'   => unserialize(hg_explore_image_link($url)),
            'size'  => $material->getHeader('content-length')[0],
        ];
    }

    /**
     * 音频amr转mp3
     * @param $upload_path
     * @param $amr_name
     * @return string
     */
    private function amr2mp3($upload_path,$amr_name){
        $mp3_name = md5(request('server_id')) . '.mp3';
        $dstPath = config('qcloud.folder').'/alive/'.request('type').'/'.$mp3_name;
        event(new AudioTranscodeEvent($upload_path,$amr_name,$mp3_name,$dstPath));

        return [
            'url' => 'http://'.config('qcloud.cos.bucket').'-'.config('qcloud.appid').'.cos'.config('qcloud.region').'.myqcloud.com/'.$dstPath,
            'size'  => filesize($upload_path.$amr_name),
        ];
    }

    private function uploadQiniu($upload_path, $file_name){
        $qiniu = config('filesystems.disks.qiniu');
        $access_key = $qiniu['access_key'];
        $secret_key = $qiniu['secret_key'];
        $bucket = $qiniu['bucket'];
        $domain = $qiniu['domains']['default'];
        $notify_url = $qiniu['notify_url'];
        $auth = new Auth($access_key, $secret_key);
        $file = $upload_path.$file_name;
        $key = '/material/live/voice/'.$file_name;
        $mp3 = md5($key).'mp3';
        $output_key = '/material/live/voice/'.$mp3;
        $save_key = base64_urlSafeEncode($bucket.':'.$output_key);
        $pfop = 'avthumb/mp3/aq/9/ar/11025|saveas/'.$save_key;
        $policy = [
            'persistentOps' => $pfop,
            'persistentNotifyUrl' => $notify_url,
        ];

        $token = $auth->uploadToken($bucket, null, 3600, $policy);
        $upload_manage = new UploadManager();
        list($ret, $err) = $upload_manage->putFile($token, $key, $file);
        if($err){
            $this->output('upload-voice-error');
        }
        return [
            'url' => 'http://'.$domain.'/'.$output_key,
        ];
    }


    private function uploadCos($path,$file_name){
        $cos_path = config('qcloud.folder').(request('indexpic')?'/':'/alive/'.request('type')).'/'.$file_name;
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$path,$cos_path);
        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        unlink($path);
        return $data['data']['source_url'];

    }

    public function uploads(){
        $this->validateWithAttribute(['file' => 'required'], ['file' => '文件']);
        if(request('sign')=='smartcity'){
            $file = request('file');
            switch (request('type')) {
                case 'voice':
                    $file_name = md5($file['localPath'] . time()) . '.mp3';
                    $cos_path = config('qcloud.folder') . '/' . request('type') . '/' . $file_name;
                    $content = base64_decode(stripcslashes($file['audioData']));
                    break;
                default :
                    $file_name = md5($file['name'] . time()) . '.jpg';
                    $cos_path = config('qcloud.folder') . '/image/' . $file_name;
                    $content = base64_decode(stripcslashes($file['imageData']));
                    break;
            }

        }else {
            $file = $_FILES['file'];
            switch (request('type')) {
                case 'voice':
                    $file_name = md5($file['name'] . time()) . '.mp3';
                    $cos_path = config('qcloud.folder') . '/' . request('type') . '/' . $file_name;
                    break;
                case 'ttf':
                    $file_name = md5($file['name'] . time()) . '.ttf';
                    $cos_path = config('qcloud.folder') . '/' . request('type') . '/' . $file_name;
                    break;
                default :
                    $file_name = md5($file['name'] . time()) . '.jpg';
                    $cos_path = config('qcloud.folder') . '/image/' . $file_name;
                    break;
            }
            $content = file_get_contents($file['tmp_name']);
        }
        $upload_path = resource_path('material/h5/');
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, 1);
        }
        file_put_contents($upload_path . $file_name, $content);
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$upload_path.$file_name,$cos_path);
        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        unlink($upload_path . $file_name);
        if(!in_array(request('type'),['voice','ttf'])){ //如果是图片类型则转换url
            $data['data']['source_url'] = hg_unserialize_image_link($data['data']['source_url']);
        }
        return $this->output(['url'=>$data['data']['source_url']]);
    }


    /**
     * 腾讯云上传文件签名
     * @return \Illuminate\Http\JsonResponse
     */
    public function signature()
    {
        $key = 'upload:signature';
        if((!$sign = Cache::get($key)) || (Redis::ttl(config('cache.prefix').':'.$key) <= 30)){
            $appid = config('qcloud.appid');
            $bucket = config('qcloud.cos.bucket');
            $secret_id = config('qcloud.secret_id');
            $secret_key = config('qcloud.secret_key');
            $expired = time() + config('qcloud.cos.signature_expire_time');
            $current = time();
            $rdm = rand();

            $multi_effect_signature = 'a='.$appid.'&b='.$bucket.'&k='.$secret_id.'&e='.$expired.'&t='.$current.'&r='.$rdm.'&f=';
            $sign = base64_encode(hash_hmac('SHA1', $multi_effect_signature, $secret_key, true).$multi_effect_signature);
            Cache::put($key,$sign,config('qcloud.cos.signature_expire_time')/60);
        }
        return $this->output(['sign'=>$sign]);
    }

    public function change()
    {
        $avlive = AliveMessage::where('type',3)->get();
        if($avlive){
            foreach ($avlive as $item){
                if($item && $item->audio){
                    $audio = unserialize($item->audio);
                    if(isset($audio['server_id']))
                    {
                        $serve_id = $audio['server_id'];
                        $file_name = md5($serve_id).'.amr';
                        $upload_path = resource_path('material/h5/');
                        if (!is_dir($upload_path)) {
                            mkdir($upload_path, 0777, 1);
                        }
                        if(file_exists($upload_path.$file_name)) {
                            $mp3_name = md5($serve_id) . '.mp3';
                            $dstPath = config('qcloud.folder').'/alive/voice/'.$mp3_name;
                            event(new AudioTranscodeEvent($upload_path, $file_name,$mp3_name,$dstPath));
                            $id[] = [
                                $dstPath,
                                $upload_path.$file_name,
                                $item->id
                            ];
                        }
                    }

                }
            }
        }
        return isset($id) ? $id : [];
    }
}