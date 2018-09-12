<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\VideoClass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ChangeVideoClass extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'change:video:class';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'change video class name';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if(Redis::exists('video:offset')){
            $offset = intval(Redis::get('video:offset'));
            if($offset == -1) return false;
        }else{
            $offset = 0;
        }
        $data = Material::where('type','video')
            ->orderBy('id','asc')
            ->offset($offset)
            ->limit(1)->first();
        if($data){
            Redis::set('video:offset',$offset+1);
            if($data->content){
                $video = unserialize($data->content) ?: [];
                $file_id = $video['file_id'];
                $shop_id = $data->shop_id;
                return $this->changeVideoClass($file_id,$shop_id);
            }
        }else{
            Redis::set('video:offset',-1);
            return false;
        }
    }

    private function changeVideoClass($file_id,$shop_id)
    {
        if($class_id = $this->getVideoClassId($shop_id)){
            $param = [
                'classId'   => $class_id,
                'Action'    => 'ModifyVodInfo',
                'fileId'    => $file_id,
            ];

            $result = \QcloudApi_Common_Request::send($param, config('qcloud.secret_id'), config('qcloud.secret_key'), 'GET',
                config('qcloud.delete.host'), config('qcloud.delete.path'));
            if(!$result){
                file_put_contents(storage_path('logs/modify.txt'),'qcloud_error:'.$file_id.'|'.$class_id.'|'.$shop_id."\n\n",FILE_APPEND);
                return false;
            }
            if($result['code']){
                file_put_contents(storage_path('logs/modify.txt'),'modify_class_error:'.$result['message'].$result['code'].'|'.$file_id.'|'.$class_id.'|'.$shop_id."\n\n",FILE_APPEND);
                return false;
            }else{
                file_put_contents(storage_path('logs/modify.txt'),$file_id.'|'.$class_id.'|'.$shop_id."\n\n",FILE_APPEND);
                return false;
            }
        }
    }

    private function getVideoClassId($shop_id)
    {
        $parentId = config('qcloud.classId');
        $key = 'newvideo:class:'.$parentId.':'.$shop_id;
        if(Redis::exists($key)){
            return Redis::get($key);
        }elseif($id = VideoClass::where('shop_id',$shop_id)->where('parent_id',$parentId)->value('class_id')){
            Redis::set($key,$id);
            return $id;
        }else{
            $id = $this->createVideoClass($shop_id);
            $this->saveShopClassId($id,$shop_id);
            Redis::set($key,$id);
            return $id;
        }
    }

    private function saveShopClassId($id,$shop_id)
    {
        $vc = new VideoClass;
        $vc->shop_id = $shop_id;
        $vc->class_id = $id;
        $vc->parent_id = config('qcloud.classId');
        $vc->save();
    }

    private function createVideoClass($shop_id)
    {
        $param = [
            'className' => $shop_id,
            'Action'    => 'CreateClass',
            'parentId'  => config('qcloud.classId'),
        ];

        $result = \QcloudApi_Common_Request::send($param, config('qcloud.secret_id'), config('qcloud.secret_key'), 'GET',
            config('qcloud.delete.host'), config('qcloud.delete.path'));
        if(!$result){
            file_put_contents(storage_path('logs/modify.txt'),'qcloud_error:'.$shop_id."\n\n",FILE_APPEND);
            return false;
        }
        if($result['code']){
            file_put_contents(storage_path('logs/modify.txt'),$result['code'].'-'.$result['message'].':'.$shop_id."\n\n",FILE_APPEND);

            if($result['code'] == 4000)
            {
                $vc = VideoClass::where('shop_id',$this->shop['id'])
                    ->first();
                if( !$vc->parent_id ){
                    $this->saveShopClassId($vc->class_id);
                }
            }
            return false;
        }else{
            if($result['newClassId']){
                return $result['newClassId'];
            }
        }
    }
}