<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/7/24
 * Time: 10:21
 */

namespace App\Http\Controllers\Manage\Code;


use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\InviteCardTemplate;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Facades\Image;
use Intervention\Image\Imagick;

class CardController extends BaseController
{

    /**
     * @return mixed
     * 测试数据测试数据测试数据
     */
//    public function templateCreate()
//    {
//        $param = [
//            'title' => '正规模板3', //模板标题
//            'indexpic' => 'http://duanshu-1253562005.picsh.myqcloud.com/dsapply/image/1501728403671_429223.png', //模板背景图
//            'brief' => '模板描述3',
//            'content' => [
//                'indexpic' => [
//                    'width' => 690,
//                    'height' => 510,
//                    'position' => 'top-left',
//                    'x' => '0',    //x坐标
//                    'y' => '0',    //y坐标
//                ],
//                'avatar' => [
//                    'width' => 64,
//                    'height' => 64,
//                    'position' => 'top-center',
//                    'x' => '345',    //x坐标
//                    'y' => '144',    //y坐标
//                ],
//                'qrcode' => [
//                    'width' => 148,
//                    'height' => 148,
//                    'position' => 'top-center',
//                    'x' => '345',    //x坐标
//                    'y' => '700',    //y坐标
//                ],
//                'param' => [
//                    'nick_name' => [
//                        'file' => 'http://duanshu-1253562005.cossh.myqcloud.com/test/dsapply/ttf/835b91d27c00e62689a050d9cd564afc.ttf',  //字体格式链接
//                        'content' => '安小毒',
//                        'color' => '#757368',      //文字颜色
//                        'size' => '28', //文字尺寸，
//                        'align' => 'center',   //left, right and center Default: left
//                        'valign' => 'top',   //top bottom and middle Default: bottom
//                        'angle' => '0',    //倾斜度
//                        'x' => '345',    //x坐标
//                        'y' => '226',    //y坐标
//                    ],
////                    'time' => [
////                        'file' => resource_path('material/card/ttf/simhei.ttf'),  //字体格式
////                        'content' => '04-07 20:00',
////                        'color' => '#858585',      //文字颜色
////                        'size' => '24', //文字尺寸，
////                        'align' => 'left',   //left, right and center Default: left
////                        'valign' => 'top',   //top bottom and middle Default: bottom
////                        'angle' => '0',    //倾斜度
////                        'x' => '340',    //x坐标
////                        'y' => '178',    //y坐标
////                    ],
//                    'title' => [
//                        'file' => '/Users/mac/an/hoge-tech-api/resources/material/card/ttf/simhei.ttf',  //字体格式
//                        'content' => '专栏或者内容名称',
//                        'color' => '#d0b282',      //文字颜色
//                        'size' => '48', //文字尺寸，
//                        'align' => 'center',   //left, right and center Default: left
//                        'valign' => 'top',   //top bottom and middle Default: bottom
//                        'angle' => '0',    //倾斜度
//                        'x' => '345',    //x坐标
//                        'y' => '396',    //y坐标
//                    ],
//                    'shop' => [
//                        'file' => '/Users/mac/an/hoge-tech-api/resources/material/card/ttf/simhei.ttf',  //字体格式
//                        'content' => '安小毒的店铺',
//                        'color' => '#d0b282',      //文字颜色
//                        'size' => '24', //文字尺寸，
//                        'align' => 'center',   //left, right and center Default: left
//                        'valign' => 'top',   //top bottom and middle Default: bottom
//                        'angle' => '0',    //倾斜度
//                        'x' => '345',    //x坐标
//                        'y' => '648',    //y坐标
//                    ],
//
//                ]
//            ]
//        ];
////        return serialize($param['content']);die;
//        $img = Image::make($param['indexpic'])->resize(690,975);
////        $img = Image::canvas(345,488,'#ffffff');
//        foreach ($param['content']['param'] as $item) {
//
//            $img->text($item['content'], $item['x'], $item['y'], function ($font) use ($item){
//                $font->file($item['file']);
//                $font->size($item['size']);
//                $font->color($item['color']);       //
//                $font->align($item['align']);    //left, right and center Default: left
//                $font->valign($item['valign']);   //top bottom and middle Default: bottom
//                $font->angle($item['angle']);       //倾斜
//            });
//        }
//
//        $headimgurl = resource_path('material/card/image/0.jpg');
//
//        $imgs['dst'] = $headimgurl;
//        //第一步 压缩图片
//        $imggzip = $this->resize_img($headimgurl);
//        //第二步 裁减成圆角图片
//        $imgs['src'] = $this->test($imggzip);
//
////        $img->insert(Image::make(resource_path('material/card/image/001.png'))->resize(690,510),'top-left',0,0);
//        $img->insert($imgs['src'],'top-center',345,144);
//        $img->insert(Image::make(resource_path('material/card/image/02.png'))->resize(148,148),'top-center',345,700);
//
//        $img->save(resource_path('material/card/image/222.jpg'));
//        return $img;
//}

    public function resize_img($url){
        $imgname = resource_path('material/card/image/').uniqid().'.jpg';
        $file = $url;
        list($width, $height) = getimagesize($file); //获取原图尺寸
        $percent = (64/$width);
        //缩放尺寸
        $newwidth = $width * $percent;
        $newheight = $height * $percent;
        $src_im = imagecreatefromjpeg($file);
        $dst_im = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresized($dst_im, $src_im, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        imagejpeg($dst_im, $imgname); //输出压缩后的图片
        imagedestroy($dst_im);
        imagedestroy($src_im);
        return $imgname;
    }


    private function test($url){
        $w = 64;  $h=64; // original size
        $original_path= $url;
        $dest_path = resource_path('material/card/image/').uniqid().'.png';
        $src = imagecreatefromstring(file_get_contents($original_path));
        $newpic = imagecreatetruecolor($w,$h);
        imagealphablending($newpic,false);
        $transparent = imagecolorallocatealpha($newpic, 0, 0, 0, 127);
        $r=$w/2;
        for($x=0;$x<$w;$x++)
            for($y=0;$y<$h;$y++){
                $c = imagecolorat($src,$x,$y);
                $_x = $x - $w/2;
                $_y = $y - $h/2;
                if((($_x*$_x) + ($_y*$_y)) < ($r*$r)){
                    imagesetpixel($newpic,$x,$y,$c);
                }else{
                    imagesetpixel($newpic,$x,$y,$transparent);
                }
            }
        imagesavealpha($newpic, true);
        imagepng($newpic, $dest_path);
        imagedestroy($newpic);
        imagedestroy($src);
        unlink($url);
        return $dest_path;
    }


    /**
     * 新增模板
     * @return mixed
     */
    public function saveTemplate(){
        $this->validateWithAttribute(
            [
                'title'         =>'required|string',
                'indexpic'      =>'required|string',
                'backgroundpic' => 'required|string',
                'brief'         => 'required|string',
                'content'       => 'required'
            ],['title'=>'标题','indexpic'=>'索引图','backgroundpic'=>'背景图','brief'=>'简介','content'=>'内容']
        );
        $content = $this->validateContent();
        $data = $this->getParams(1,$content);
        $invite = new InviteCardTemplate();
        $invite->setRawAttributes($data);
        $invite->saveOrFail();
        $invite->create_time = date('Y-m-d H:i:s',$invite->create_time);
        $invite->content = unserialize($invite->content);
        return $invite;
    }

    /**
     * 验证content参数
     */
    private function validateContent(){
        $content = request('content');
        $first = ['indexpic','avatar','qrcode','param'];
        $second = ['is_use','width','height','position','x','y'];
        $third = ['nick_name','time','title','shop'];
        $forth = ['is_use','file','color','size','align','valign','angle','x','y'];
        $real_content = [];

        foreach ($first as $item){   //判断content是否包含'indexpic','avatar','qrcode','param'
            $contents[$item] = isset($content[$item]) ? $content[$item] : '';
        }
        foreach ($contents as $key=>$value){
            if($key == 'indexpic' || $key == 'avatar' || $key == 'qrcode'){ //判断'indexpic','avatar','qrcode'是否包含'is_use','width','height','position','x','y'
                foreach ($second as $items){
                    $values[$items] = isset($value[$items]) ? $value[$items] : '';
                }
                $real_content[$key] = $values;

            }
        }
        foreach ($contents['param'] as $key=>$param){
                foreach ($third as $thirds){ //判断param是否包含 'nick_name','time','title','shop'
                    $params[$thirds] = isset($contents['param'][$thirds]) ? $contents['param'][$thirds] : '';
                        foreach ($forth as $for){//'nick_name','time','title','shop'是否包含'is_use','file','color','size','align','valign','angle','x','y'
                            $vals[$for] = isset($params[$thirds][$for]) ? $params[$thirds][$for] : '';
                        }
                        $real_content['param'][$thirds]=$vals;
                }
        }
        return $real_content;
    }

    private function getParams($sign = 0,$content){
        $data = [
            'title'         => request('title'),
            'indexpic'      => request('indexpic'),
            'backgroundpic' => request('backgroundpic'),
            'brief'         => request('brief'),
            'content'       => serialize($content),
        ];
        $sign && $data['create_time'] = time();
        return $data;
    }

    /**
     * 模板详情
     */
    public function getDetail(){
        $this->validateWithAttribute(['id'=>'required|numeric',],['id'=>'模板id']);
        $result = InviteCardTemplate::where('id',request('id'))->firstOrFail();
        $result->content = unserialize($result->content);
        $result->create_time = date('Y-m-d H:i:s',$result->create_time);
        return $this->output($result);
    }

    /**
     * 更新模板
     * @return mixed
     */
    public function updateTemplate(){
        $this->validateWithAttribute(
            [
                'id'            => 'required|numeric',
                'title'         =>'required|string',
                'indexpic'      =>'required|string',
                'backgroundpic' => 'required|string',
                'brief'         => 'required|string',
                'content'       => 'required'
            ],['id'=>'模板id','title'=>'标题','indexpic'=>'索引图','backgroundpic'=>'背景图','brief'=>'简介','content'=>'内容']
        );
        $content = $this->validateContent();
        $data = $this->getParams(0,$content);
        $invite = InviteCardTemplate::findOrFail(request('id'));
        $invite->setRawAttributes($data);
        $invite->saveOrFail();
        $invite->create_time = date('Y-m-d H:i:s',$invite->create_time);
        $invite->content = unserialize($invite->content);
        Cache::forget('card:template:'.request('id'));
        return $invite;
    }

    /**
     * 删除模板
     * @return mixed
     */
    public function deleteTemplate(){
        $this->validateWithAttribute(['id'=>'required|numeric',],['id'=>'模板id']);
        InviteCardTemplate::where('id',request('id'))->delete();
        Cache::forget('card:template:'.request('id'));
        return $this->output(['success' => 1]);
    }

    /**
     * 设置启用停用
     */
    public function changeStatus(){
        $this->validateWithAttribute(['id' => 'required|numeric', 'status' =>'required|numeric|in:0,1',],['id'=>'模板id','status'=>'状态']);
        $invite = InviteCardTemplate::findOrFail(request('id'));
        $invite->status = request('status');
        $invite->saveOrFail();
        $invite->create_time = date('Y-m-d H:i:s',$invite->create_time);
        $invite->content = unserialize($invite->content);
        return $invite;
    }

    /**
     * 排序
     * @return mixed
     */
    public function templateOrder(){
        $this->validateWithAttribute(['id' => 'required|regex:/^[0-9]\d*(,\d*)*$/'],['id'=>'模板id']);
        $ids = explode(',',request('id'));
        foreach (array_reverse($ids) as $key => $value){
            InviteCardTemplate::where('id',$value)
                ->update(['order_id' => $key]);
        }
        return $this->output(['success' => 1]);
    }

    /**
     * 模板列表
     * @return mixed
     */
    public function Lists(){
        $count = request('count') ? : 10;
        $result = InviteCardTemplate::select('*')
            ->orderBy('order_id','desc')
            ->paginate($count);
        foreach ($result->items() as $item){
            $item->create_time = date('Y-m-d H:i:s',$item->create_time);
            $item->content = $item->content ? unserialize($item->content) : [];
            $item->status  = $item->status = 1 ? true : false;
        }
        return $this->output($this->listToPage($result));
    }



}