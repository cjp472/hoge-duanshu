<?php
/**
 * Created by PhpStorm.
 * User: Janice
 * Date: 2018/6/10
 * Time: 18:22
 */

namespace App\Listeners;


use App\Models\Material;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use qcloudcos\Cosapi;
use App\Events\ClearMaterial as ClearMaterialEvent;

class ClearMaterial implements ShouldQueue
{
    use InteractsWithQueue;
    /**
     * 队列名称
     * @var string
     */
    public $queue = DEFAULT_QUEUE;

    public function handle(ClearMaterialEvent $event)
    {
        Material::where('shop_id',$event->shop_id)->where('is_del',0)
            ->chunk(100,function ($mat) {
            foreach ($mat as $item) {
                $con = unserialize($item->content);
                if( $item->type == 'audio' || $item->type == 'image'){
                    $URL = unserialize($con['url']);
                    $path = $URL['file'];
                    Cosapi::setRegion(config('qcloud.region'));
                    $result = Cosapi::delFile(config('qcloud.cos.bucket'),$path);
                }elseif( $item->type == 'video') {
                    if(isset($con['file_id']) && $con['file_id'])
                    {
                        $param = [
                            'Action' => 'DeleteVodFile',
                            'fileId' => $con['file_id'],
                            'priority'  => 2,
                        ];
                        $result = \QcloudApi_Common_Request::send(
                            $param,
                            config('qcloud.secret_id'),
                            config('qcloud.secret_key'),
                            'GET',
                            config('qcloud.delete.host'),
                            config('qcloud.delete.path')
                        );
                    }
                }

                if(isset($result)  && $result['code']){
                    file_put_contents(storage_path('logs/modify.txt'),'modify_class_error:'.$result['message'].$result['code'].'-'.$item->id."\n\n",FILE_APPEND);
                }
            }
        });
        Material::where('shop_id',$event->shop_id)->update(['is_del'=>1]);
    }

}