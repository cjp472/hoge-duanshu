<?php
namespace App\Http\Controllers\Manage\Material;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Alive;
use App\Models\Manage\Material;
use App\Models\Manage\Video;
use QcloudApi_Common_Request;

class MaterialController extends BaseController
{
    /**
     * 素材列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function lists()
    {
        $this->validateWith([
            'type'   => 'required|alpha_dash|in:text,image,audio,video',
            'count'  => 'numeric',
            'sign'  => 'required|alpha_dash|in:courseware,manage'
        ]);
        $count = request('count') ? : 15;
        $material = Material::where(['type'=>request('type'),'sign'=>request('sign')])->orderBy('created_at','desc')->paginate($count);
        if ($material->items()) {
            foreach ($material->items() as $item) {
                switch (request('type')) {
                    case 'image':
                        $item->content = $item->content ? unserialize($item->content) : [];
                        $item->indexpic = $item->content['url'] ? hg_unserialize_image_link($item->content['url']) : '';
                        break;
                    case 'video':
                        $item->content = $item->content ? unserialize($item->content) : [];
                        $item->indexpic = $item->content['cover_url'] ? hg_unserialize_image_link($item->content['cover_url']) : '';
                        break;
                    default:
                        $item->content = $item->content ? unserialize($item->content) : [];
                        $item->indexpic = '';
                        break;
                }

            }
        }
        return $this->output($this->listToPage($material));
    }

    /**
     *根据id删除素材
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteMaterial()
    {
        $this->validateWith([
            'id'    =>   'required|numeric',
            'sign'  =>   'required|alpha_dash|in:courseware,manage'
        ]);

        $result = Material::where(['id' => request('id'),'sign' => request('sign')])->firstOrFail();
        $type = $result->type;

        if($type == 'video'){
            $flag = $this->isUsed($result->content);
            $flag == 1 && $this->error('video-userd');
            $this->deleteCloudVideo($result->content);
        }elseif($type == 'image' || $type == 'audio'){
//            $this->deleteCloudImage($result->content);
        }
        Material::where('id',request('id'))->delete();
        return $this->output(['success'=>1]);
    }


    /**
     * 判断video是否在使用
     * @param $data
     * @return int
     */
    private function isUsed($data)
    {
        $content = unserialize($data);
        $file_id = $content['file_id'];
        $video = Video::where('file_id',$file_id)->get()->toArray();
        $live = Alive::where('file_id',$file_id)->get()->toArray();
        if ($video || $live) {
            return 1;
        } else {
            return 0;
        }
    }

    private function deleteCloudVideo($data){
        $content = unserialize($data);
        $file_id = $content['file_id'];
        $param = ['fileId' => $file_id,'priority' => 0,'Action'=>'DeleteVodFile'];
        $result = QcloudApi_Common_Request::send($param, config('qcloud.secret_id'), config('qcloud.secret_key'), 'GET',
            config('qloud.delete.host'), config('qloud.delete.path'));
        if ($result['code'] != 0) {
            $this->errorWithText($result['code'],$result['message']);
        } else {
            return true;
        }
    }


    /**
     * 素材不合法图片替换
     * @return \Illuminate\Http\JsonResponse
     */
    public function illegalReplace()
    {
        $this->validateWith([
           'material_id'   => 'required|numeric'
        ]);
        $content = serialize(['url'=>config('define.default_pic')]);
        $material = Material::findOrFail(request('material_id'));
        $material->content = $content;
        $material->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 字体上传
     */
    public function fontUpload(){
        $this->validateWithAttribute(['file'=>'required'],['file'=>'字体文件']);
        $file = $_FILES['file'];
        $file_name = md5($file['name'].time()).'.ttf';
        $cos_path = config('qcloud.folder').'/font/'.$file_name;
        $content = file_get_contents($file['tmp_name']);
        $upload_path = resource_path('material/h5/');
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, 1);
        }
        file_put_contents($upload_path . $file_name, $content);
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$upload_path.$file_name,$cos_path);
        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        unlink($upload_path . $file_name);
        return $this->output(['url'=>$data['data']['source_url']]);
    }
}