<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/7/24
 * Time: 09:55
 */

namespace App\Http\Controllers\H5\Code;


use App\Http\Controllers\Admin\OpenPlatform\CoreTrait;
use App\Http\Controllers\H5\BaseController;
use App\Models\Column;
use App\Models\Course;
use App\Models\Content;
use App\Models\FightGroup;
use App\Models\FightGroupActivity;
use App\Models\InviteCardTemplate;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\OpenPlatformApplet;
use App\Models\Shop;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Intervention\Image\Facades\Image;
use qcloudcos\Cosapi;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Http\Controllers\Admin\OpenPlatform\Publics\QcloudController as qcloud;


class CardController extends BaseController
{

    use CoreTrait;
    /**
     * 邀请卡制作数据处理
     */
    public function inviteCard(){
        $this->validateParam();
        if($card_cache = Cache::get('invite:card:' . md5(request('content_type').request('content_id').$this->member['id']))){
            return $this->output(['img_string'=>$card_cache]);
        }else {
            $template = $this->getTemplate();
            $content = $this->getContent();
            $this->make($template, $content);
            $img_base64 = $this->imgToBase64($content);
//            $card = $this->uploadCos($content);
//            $card_url = hg_explore_image_link($card);
            Cache::put('invite:card:' . md5($content->type . $content->content_id.request('template_id').$this->member['id']), $img_base64, 10);
            return $this->output(['img_string'=>$img_base64]);
        }

    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 邀请卡制作数据处理(小程序用)
     */
    public function appletInviteCard(){
        $this->validateParam();
        if($card_cache = Cache::get('invite:card:' . md5(request('content_type').request('content_id').$this->member['id']))){
            return $this->output(['url'=>hg_unserialize_image_link($card_cache)]);
        }else {
            $template = $this->getTemplate();
            $content = $this->getContent();
            $this->make($template, $content);
            $card = $this->uploadCos($content);
            $card_url = hg_explore_image_link($card);
            Cache::put('invite:card:' . md5($content->type . $content->content_id.request('template_id').$this->member['id']), $card_url, 30);
            return $this->output(['url'=>hg_unserialize_image_link($card_url)]);
        }
    }


    /**
     * 验证请求参数
     */
    private function validateParam(){
        $this->validateWithAttribute([
            'template_id'  => 'required|numeric',
            'content_id'    => 'required|alpha_dash|min:12',
            'content_type'  => 'required|alpha_dash|min:3|max:12',
        ],[
            'template_id'   => '模板id',
            'content_id'    => '内容id',
            'content_type'  => '内容分类',
        ]);
    }

    /**
     * 获取模板数据
     */
    private function getTemplate(){

        $template = Cache::get('card:template:'.request('template_id'));
        if($template){
            return json_decode($template);
        }
        $result = InviteCardTemplate::findOrFail(request('template_id'));
        Cache::forever('card:template:'.request('template_id'),json_encode($result));
        return $result;


    }

    /**
     * 获取内容数据
     */
    private function getContent(){
        if(request('content_type') == 'column') {
            $content = Column::where(['hashid' => request('content_id')])->firstOrFail(['hashid as content_id', 'shop_id', 'indexpic', 'title', 'brief', 'create_time as time']);
            $content->type = 'column';
            $content->shop = Shop::where('hashid', $this->shop['id'])->value('title');
            $indexpic = hg_unserialize_image_link($content->indexpic);
            $content->indexpic = $indexpic['host'] . $indexpic['file'];
        }elseif(request('content_type') == 'course'){
            $content = Course::where(['hashid' => request('content_id')])->first(['hashid as content_id', 'shop_id', 'indexpic', 'title', 'brief','course_type', 'create_time as time']);
            $content->type = 'course';
            $content->shop = Shop::where('hashid', $this->shop['id'])->value('title');
            $indexpic = hg_unserialize_image_link($content->indexpic);
            $content->indexpic = $indexpic['host'] . $indexpic['file'];
        }elseif (request('content_type') == 'fight'){
            $content = FightGroupActivity::findOrFail(request('content_id'),['id as content_id','name as title','origin_price','now_price','redundancy_product','product_category as content_type','product_identifier as content']);
            $content->origin_price = $content->origin_price ? round($content->origin_price/100,2) : 0;
            $content->now_price = $content->now_price ? round($content->now_price/100,2) : 0;
            $content->redundancy_product = $content->offsetExists('redundancy_product') ? json_decode($content->redundancy_product) : [];
            $indexpic = $content->redundancy_product->indexpic;
            if(isset($indexpic->host) && isset($indexpic->file)){
                $content->indexpic = $indexpic->host.$indexpic->file;
            }else{
                $content->indexpic = $indexpic;
            }
            $content->type = 'fight';
            $content->fight_group_id = request('fight_group_id');
            $content->shop_id = $this->shop['id'];
            $content->offsetUnset('redundancy_product');
        }
        elseif (request('content_type') == 'member_card'){
            $content = MemberCard::where(['hashid'=>request('content_id')])->firstOrFail(['hashid as content_id', 'title', 'price']);
            $content->type = 'member_card';
            $content->shop = Shop::where('hashid', $this->shop['id'])->value('title');
            $content->shop_id = $this->shop['id'];
            $content->indexpic = [];
        }else{
            $content = Content::where(['hashid'=>request('content_id'),'type'=>request('content_type')])->firstOrFail(['hashid','shop_id','indexpic','type','title','brief','create_time as time']);
            $content->content_id = $content->hashid;
            $content->time = (request('content_type')=='live')?$content->alive->start_time:'';
            $content->shop = Shop::where('hashid',$this->shop['id'])->value('title');
            $indexpic = hg_unserialize_image_link($content->indexpic);
            $content->indexpic = $indexpic['host'].$indexpic['file'] ;
        }
        $member = Member::where(['uid'=>$this->member['id']])->first(['avatar','nick_name']);
        $this->member['nick_name'] = $member->nick_name ? : '';
        $this->member['avatar'] = $member->avatar ? : '';
        return $content;
    }

    /**
     * 制作邀请卡
     * @param $template
     * @param $content
     * @param $member
     */
    private function make($template,$content){
        $temp_content = $template->content ? unserialize($template->content) : [];
        if(request('template_id')==1){
            if(request('content_type')!='live'){
                $temp_content['param']['nick_name']['y'] = $temp_content['param']['nick_name']['y']+20;
            }
            $this->member['nick_name'] = $this->member['nick_name'].' 邀请你一起学习';
        }
        if(request('content_type')=='live'){
            $content->time = hg_format_date($content->time);
        }else{
            unset($content->time);
        }
        $content = array_merge($content->toarray(),$this->member);
        if($temp_content) {
            $client = new Client();
            $path = resource_path('material/card/' . request('content_id') . '/');
            !is_dir($path) && mkdir($path, 0777, 1);

            if ($template->backgroundpic) {
                $background_path = resource_path('material/card/template/' . request('template_id') . '/');
                $background = $background_path . md5($template->backgroundpic);
                !is_dir($background_path) && mkdir($background_path, 0777, 1);
                if(!is_file($background)) {
                    $background_data = $client->request('get', $template->backgroundpic,['verify' => false])->getBody()->getContents();
                    file_put_contents($background, $background_data);
                }
            }

            $avatar_url = $content['avatar']?:config('define.default_avatar');
            $avatar_data = $client->request('get', $avatar_url,['verify' => false])->getBody()->getContents();
            $avatar = $path . md5($avatar_url.str_random(6));
            file_put_contents($avatar, $avatar_data);

            if ($content['indexpic']) {
                $pic = hg_unserialize_image_link($content['indexpic']);
                $pic['host'] = 'http://upload.duanshu.com';
                $indexpic_url = $pic['host'].$pic['file'].'?imageMogr2/auto-orient/thumbnail/!358x524r';
                $indexpic_data = $client->request('get', $indexpic_url,['verify' => false])->getBody()->getContents();
                $indexpic = $path . md5($content['indexpic'].str_random(6));
                file_put_contents($indexpic, $indexpic_data);
            }
            chmod($path, 0777);
            $img = Image::make($background);
            foreach ($temp_content['param'] as $key => $item) {
                $text = isset($content[$key]) ? $content[$key] : '';
                if (request('template_id') != 1 && $key == 'shop') {
                    $text = '- ' . $text . ' -';
                }
                if ($key == 'title') {
                    if(request('template_id')==1 || $content['type'] == 'fight'){
                        $str[0] = mb_substr($text, 0, 20, 'utf-8');
                        $str[1] = mb_substr($text, 20, 20, 'utf-8');
                    }else{
                        $str[0] = mb_substr($text, 0, 14, 'utf-8');
                        $str[1] = mb_substr($text, 14, 14, 'utf-8');
                        $str[2] = mb_substr($text, 28, 14, 'utf-8');
                    }
                    if ($str && is_array($str)) {
                        $img->text($str[0], $item['x'], $item['y'], function ($font) use ($item) {
                            $font->file(resource_path($item['file']));
                            $font->size($item['size']);
                            $font->color($item['color']);       //
                            $font->align($item['align']);    //left, right and center Default: left
                            $font->valign($item['valign']);   //top bottom and middle Default: bottom
                            $font->angle($item['angle']);       //倾斜
                        });
                        isset($str[1]) && $img->text($str[1], $item['x'], $item['y'] + $item['size'] + 10, function ($font) use ($item) {
                            $font->file(resource_path($item['file']));
                            $font->size($item['size']);
                            $font->color($item['color']);       //
                            $font->align($item['align']);    //left, right and center Default: left
                            $font->valign($item['valign']);   //top bottom and middle Default: bottom
                            $font->angle($item['angle']);       //倾斜
                        });
                        isset($str[2]) && $img->text($str[2], $item['x'], $item['y'] + $item['size']*2 + 20, function ($font) use ($item) {
                            $font->file(resource_path($item['file']));
                            $font->size($item['size']);
                            $font->color($item['color']);       //
                            $font->align($item['align']);    //left, right and center Default: left
                            $font->valign($item['valign']);   //top bottom and middle Default: bottom
                            $font->angle($item['angle']);       //倾斜
                        });
                    }
                } else {
                    $img->text($text, $item['x'], $item['y'], function ($font) use ($item) {
                        $font->file(resource_path($item['file']));
                        $font->size($item['size']);
                        $font->color($item['color']);
                        $font->align($item['align']);    //left, right and center Default: left
                        $font->valign($item['valign']);   //top bottom and middle Default: bottom
                        $font->angle($item['angle']);       //倾斜
                    });
                }
            }
            //头像处理
            $imgs['src'] = $this->resize_img($temp_content,$avatar);
            $img->insert($imgs['src'], $temp_content['avatar']['position'], $temp_content['avatar']['x'], $temp_content['avatar']['y']);
            if ((request('template_id')==1 || $content['type'] == 'fight') && isset($temp_content['indexpic'])) {
                if($content['type']=='column'){
                    $bg = Image::make($indexpic)->fit($temp_content['indexpic']['width'], $temp_content['indexpic']['height'])->blur(100);
                    $index = $bg->insert(Image::make($indexpic)->fit(420,$temp_content['indexpic']['height']),'top-center',0,0);
                    $img->insert($index, $temp_content['indexpic']['position'], $temp_content['indexpic']['x'], $temp_content['indexpic']['y']);
                }else{
                    $indexpic_image = $this->image_resize($indexpic, $temp_content['indexpic']['width'], $temp_content['indexpic']['height']);
                    if($indexpic_image){
                        $imgs['indexpic'] = $indexpic_image;
                        $img->insert($indexpic_image, $temp_content['indexpic']['position'], $temp_content['indexpic']['x'], $temp_content['indexpic']['y']);
                    }
//                    $img->insert(Image::make($indexpic)->resize($temp_content['indexpic']['width'], $temp_content['indexpic']['height']), $temp_content['indexpic']['position'], $temp_content['indexpic']['x'], $temp_content['indexpic']['y']);
                }
            }
            //二维码生成处理
            $qrcode = $this->createQrcode($content);
            $img->insert(Image::make($qrcode)->resize($temp_content['qrcode']['width'], $temp_content['qrcode']['height']), $temp_content['qrcode']['position'], $temp_content['qrcode']['x'], $temp_content['qrcode']['y']);
            file_exists($qrcode) && @unlink($qrcode);
//            @unlink($background);
            $content['indexpic'] && file_exists($indexpic) && @unlink($indexpic);
            file_exists($avatar) && @unlink($avatar);
            file_exists($imgs['src']) && @unlink($imgs['src']);
            isset($imgs['indexpic']) && file_exists($imgs['indexpic']) && @unlink($imgs['indexpic']);
            if(count(scandir($path))==2) {  //删除空目录，=2是因为./..
                rmdir($path);
            }
            //生成邀请卡
            $img->save(resource_path('material/card/image/' . md5($content['type'] . $content['content_id'] . request('template_id')) . '.jpg'));
        }
    }

    private function resize_img($temp_content,$url){
        $imgname = resource_path('material/card/image/') . uniqid() . '.jpg';
        $file = $url;
        list($width, $height) = getimagesize($file); //获取原图尺寸
        $percent = ($temp_content['avatar']['width'] / $width);
        //缩放尺寸
        $newwidth = $width * $percent;
        $newheight = $height * $percent;

        $file_dimensions = getimagesize($file); //获取图像信息
        $file_type = strtolower($file_dimensions['mime']);  //获取图像类型
        $src_im = '';
        switch($file_type) {
            case 'image/png':
                $src_im = imagecreatefrompng($file);
                break;
            case 'image/gif':
                $src_im = imagecreatefromgif($file);
                break;
            case 'image/jpeg':
            case 'image/pjpeg':
                $src_im = imagecreatefromjpeg($file);
                break;
            default:
                $this->error('WRONG_FILE_TYPE');
        }
        $dst_im = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresized($dst_im, $src_im, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        imagejpeg($dst_im, $imgname); //输出压缩后的图片
        imagedestroy($dst_im);
        imagedestroy($src_im);

        $w = $temp_content['avatar']['width'];
        $h = $temp_content['avatar']['height']; // original size
        $dest_path = resource_path('material/card/image/') . uniqid() . '.png';
        $src = imagecreatefromstring(file_get_contents($imgname));
        $newpic = imagecreatetruecolor($w, $h);
        imagealphablending($newpic, false);
        $transparent = imagecolorallocatealpha($newpic, 0, 0, 0, 127);
        $r = $w / 2;
        for ($x = 0; $x < $w; $x++)
            for ($y = 0; $y < $h; $y++) {
                $c = @imagecolorat($src, $x, $y);
                $_x = $x - $w / 2;
                $_y = $y - $h / 2;
                if ((($_x * $_x) + ($_y * $_y)) < ($r * $r)) {
                    imagesetpixel($newpic, $x, $y, $c);
                } else {
                    imagesetpixel($newpic, $x, $y, $transparent);
                }
            }
        imagesavealpha($newpic, true);
        imagepng($newpic, $dest_path);
        imagedestroy($newpic);
        imagedestroy($src);
        unlink($imgname);
        return $dest_path;
    }

    /**
     * 图片缩放裁剪
     * @param $url
     * @param $new_width
     * @param $new_height
     * @return array
     */
    private function image_resize($url, $new_width, $new_height)
    {
        $imgname = resource_path('material/card/image/') . uniqid() . '.jpg';
        $file = $url;
        list($width, $height) = getimagesize($file); //获取原图尺寸
        if($width == 0 || $height==0){
            return;
        }
        $source_ratio  = $height / $width;
        $target_ratio  = $new_height / $new_width;
        $file_dimensions = getimagesize($file); //获取图像信息
        $file_type = strtolower($file_dimensions['mime']);  //获取图像类型
        $src_im = '';
        switch ($file_type) {
            case 'image/png':
                $src_im = imagecreatefrompng($file);
                break;
            case 'image/gif':
                $src_im = imagecreatefromgif($file);
                break;
            case 'image/jpeg':
            case 'image/pjpeg':
                $src_im = imagecreatefromjpeg($file);
                break;
            default:
                $this->error('WRONG_FILE_TYPE');
        }
        //缩放
        $dst_im = imagecreatetruecolor($new_width, $new_height);
        if ($source_ratio > $target_ratio) {
            $resize_width = $new_width;
            $resize_height = $new_width * $source_ratio;
        } else if ($source_ratio < $target_ratio) {
            $resize_height = $new_height;
            $resize_width = $new_width / $source_ratio;
        } else {
            $resize_width = $new_width;
            $resize_height = $new_height;
        }
        imagecopyresized($dst_im, $src_im, 0, 0, 0, 0, $resize_width, $resize_height, $width, $height);

        // 裁剪
        if ($resize_width == $new_width && $resize_height == $new_height) {
            $cropped_width = $width;
            $cropped_height = $height;
            $source_x = 0;
            $source_y = 0;
        } else if ($resize_width != $new_width) {
            $cropped_height = $resize_height;
            $cropped_width = $cropped_height / $target_ratio;
            $source_x = ($resize_width - $cropped_width) / 2;
            $source_y = 0;
        } else {
            $cropped_width = $resize_width;
            $cropped_height = $cropped_width * $target_ratio;
            $source_x = 0;
            $source_y = ($resize_height - $cropped_height) / 2;
        }
        $cropped_im = imagecreatetruecolor($cropped_width, $cropped_height);
        //图片裁剪
        imagecopy($cropped_im, $dst_im, 0, 0, $source_x, $source_y, $cropped_width, $cropped_height);

        imagejpeg($cropped_im, $imgname); //输出压缩后的图片
        imagedestroy($cropped_im);
        imagedestroy($dst_im);
        imagedestroy($src_im);


        $w = $new_width;
        $h = $new_height; // original size
        $dest_path = resource_path('material/card/image/') . uniqid() . '.png';
        $src = imagecreatefromstring(file_get_contents($imgname));
        $newpic = imagecreatetruecolor($w, $h);
        imagealphablending($newpic, false);
        for ($x = 0; $x < $w; $x++)
            for ($y = 0; $y < $h; $y++) {
                $c = @imagecolorat($src, $x, $y);
                imagesetpixel($newpic, $x, $y, $c);
            }
        imagesavealpha($newpic, true);
        imagepng($newpic, $dest_path);
        imagedestroy($newpic);
        imagedestroy($src);
        unlink($imgname);
        return $dest_path;
    }

    /**
     * 圆图
     * @param $url
     * @return string|void
     */
    private function image_circle($url)
    {
        $file = $url;
        list($width, $height) = getimagesize($file); //获取原图尺寸
        if ($width == 0 || $height == 0) {
            return;
        }
        $dest_path = resource_path('material/card/image/') . uniqid() . '.jpg';
        $file_dimensions = getimagesize($file); //获取图像信息
        $file_type = strtolower($file_dimensions['mime']);  //获取图像类型
        $src_im = '';
        switch ($file_type) {
            case 'image/png':
                $src_im = imagecreatefrompng($file);
                break;
            case 'image/gif':
                $src_im = imagecreatefromgif($file);
                break;
            case 'image/jpeg':
            case 'image/pjpeg':
                $src_im = imagecreatefromjpeg($file);
                break;
            default:
                $this->error('WRONG_FILE_TYPE');
        }
        $newpic = imagecreatetruecolor($width, $height);
        imagealphablending($newpic, false);
        $transparent = imagecolorallocatealpha($newpic, 0, 0, 0, 127);
        $r = $width / 2;
        for ($x = 0; $x < $width; $x++)
            for ($y = 0; $y < $height; $y++) {
                $c = imagecolorat($src_im, $x, $y);
                $_x = $x - $width / 2;
                $_y = $y - $height / 2;
                if ((($_x * $_x) + ($_y * $_y)) < ($r * $r)) {
                    imagesetpixel($newpic, $x, $y, $c);
                } else {
                    imagesetpixel($newpic, $x, $y, $transparent);
                }
            }
        imagesavealpha($newpic, true);
        imagepng($newpic, $dest_path);
        imagedestroy($newpic);
        imagedestroy($src_im);
        unlink($url);
        return $dest_path;
    }


    /**
     * 生成内容二维码
     * @param $content
     * @return string
     */
    private function createQrcode($content)
    {
        $path = resource_path('material/card/qrcode/' . md5($content['type'] . $content['content_id'].str_random(6)) . '.png');

        $version = Shop::where('hashid',$this->shop['id'])->value('applet_version');
        if(request('source') == 'wx_applet' && $version == 'basic' && $content['type']=='fight'){
            //小程序
            $appid = OpenPlatformApplet::where('shop_id',$this->shop['id'])->value('appid');

            switch ($content['content_type']){
                case 'course':
                    $qrcode_path = 'pages/bricourse/bricourse?cid='.$content['content'].'&type='.$content['content_type'].'&fightgroup_id='.$content['content_id'];
                    break;
                case 'column':
                    $qrcode_path = 'pages/column/column?cid=' . $content['content'] . '&fightgroup_id='.$content['content_id'];
                    break;
                case 'article':
                case 'audio':
                case 'video':
                    $qrcode_path = 'pages/brief/brief?cid=' . $content['content'] . '&type=' .$content['content_type'] .'&fightgroup_id='.$content['content_id'];
                    break;
                default:
                    $qrcode_path = 'pages/brief/brief?cid=' . $content['content'] . '&type=' .$content['content_type'] .'&fightgroup_id='.$content['content_id'];
                    break;
            }
            $params = [
                'auto_color' => false,
                'line_color' => ['r' => 0, 'g' => 0, 'b' => 0],
                'path'       => $qrcode_path,
                'width'      => 160,
                'is_hyaline' => true,
            ];

            $cache_qrcode = Redis::hget('openflatform:appcode:' . $appid, json_encode($params));
            $cache_qrcode = json_decode($cache_qrcode,1);
            if($cache_qrcode){
                $qrcode_url = $cache_qrcode['qrcode'];
                $client = new Client();
                $qrcode_data = $client->request('get', $qrcode_url,['verify' => false])->getBody()->getContents();
                file_put_contents($path, $qrcode_data);
                return $path;

            }else {
                $this->type = 'applet';
                $authorizationData = $this->getAuthorizerAccessToken();
                $token = $authorizationData['authorizer_access_token'];
                $appid = $authorizationData['open_platform']->appid;

                $client = new Client();
                $url = config('define.open_platform.wx_applet.api.getwxacode') . '?access_token=' . $token;
                $qrcode_data = $client
                    ->request('POST', $url, ['json' => $params])
                    ->getBody()
                    ->getContents();
                if ($qrcode_data && !json_decode($qrcode_data)) {
                    $name = md5($appid . 'limit');
                    $ret = ['qrcode' => (new qcloud())->uploadImg($name, $qrcode_data)];
                    Redis::hset('openflatform:appcode:' . $appid, json_encode($params), json_encode($ret));

                    file_put_contents($path, $qrcode_data);
                    return $path;
                }
            }
        }
        if($content['type']=='course'){
            $qrcode_url = H5_DOMAIN .'/'. $content['shop_id'] . '/#/form/cource/' . $content['content_id'].'/'.$content['course_type'];
        }elseif ($content['type']=='fight'){
            $fight_group_id = $content['fight_group_id'];
            if (!$fight_group_id) {
                $this->error('fight-group-id-null');
            }
            $fight_group = FightGroup::find($fight_group_id);
            //团组不存在或者已删除
            if(!$fight_group || $fight_group->is_del || $fight_group->status == 'failed'){
                $this->error('fight-group-not-find');
            }
            $qrcode_url = H5_DOMAIN .'/'. $content['shop_id'] . '/#/fightGroup/participation/'.$fight_group_id;
        } elseif($content['type']=='member_card') {
            $qrcode_url = H5_DOMAIN . '/' . $content['shop_id'] . '/#/card/' . $content['content_id'];
        }else {
            $qrcode_url = H5_DOMAIN .'/'. $content['shop_id'] . '/#/brief/' . $content['type'] . '/' . $content['content_id'];
        }
        if (request('promoter_id')) {
            $qrcode_url = $qrcode_url . '?promoter_id=' . request('promoter_id');
        }
        QrCode::format('png')->size(100)->margin(0)->generate($qrcode_url, $path);
        return $path;
    }

    /**
     * 邀请卡图片上传到cos
     * @param $content
     * @return mixed
     */
    private function uploadCos($content){
        $file_name = md5($content->type.$content->content_id.request('template_id')).'.jpg';
        $cos_path = config('qcloud.folder').'/card/'.$file_name;
        $path = resource_path('material/card/image/'.md5($content->type.$content->content_id.request('template_id')).'.jpg');
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$path,$cos_path,null,null,'insertOnly');
        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        unlink($path);
        return $data['data']['source_url'];
    }


    private function imgToBase64($content) {
        $file_name = md5($content->type.$content->content_id.request('template_id')).'.jpg';
        $img_file = resource_path('material/card/image/'.$file_name);
        $img_base64 = '';
        if (file_exists($img_file)) {
            $app_img_file = $img_file; // 图片路径
            $img_info = getimagesize($app_img_file); // 取得图片的大小，类型等
            $fp = fopen($app_img_file, "r"); // 图片是否可读权限
            if ($fp) {
                $filesize = filesize($app_img_file);
                $img_content = fread($fp, $filesize);
                $file_content = chunk_split(base64_encode($img_content)); // base64编码
                switch ($img_info[2]) {           //判读图片类型
                    case 1: $img_type = "gif";break;
                    case 2: $img_type = "jpg";break;
                    case 3: $img_type = "png";break;
                }
                $img_base64 = 'data:image/' . $img_type . ';base64,' . $file_content;//合成图片的base64编码
            }
            fclose($fp);
        }
        unlink($img_file);
        return $img_base64; //返回图片的base64
    }




    /**
     * 模板列表
     * @return mixed
     */
    public function templateList(){
        $count = request('count') ? : 10;
        $result = InviteCardTemplate::where('status',1)
            ->orderBy('order_id','desc')
            ->select('id','indexpic','title','create_time','order_id','content','backgroundpic')
            ->paginate($count);
        if($result->total() > 0){
            foreach ($result as $item){
                $item->create_time = date('Y-m-d H:i:s',$item->create_time);
                $item->content = $item->content ? unserialize($item->content) : [];
            }
        }
        return $this->output($this->listToPage($result));
    }

    /**
     * 邀请卡页面获取内容信息
     */
    public function inviteCardContentInfo(){

        $this->validateWithAttribute([
            'content_id'    => 'required|alpha_dash',
            'content_type'  => 'required|alpha_dash',
        ],[
            'content_id'    => '内容id',
            'content_type'  => '内容分类'
        ]);

        switch (request('content_type')){
            case 'column':
                $content = Column::where(['hashid' => request('content_id')])->firstOrFail(['hashid as content_id', 'shop_id', 'indexpic', 'title', 'brief', 'create_time as time']);
                $content->type = 'column';
                break;
            case 'course':
                $content = Course::where(['hashid' => request('content_id')])->firstOrFail(['hashid as content_id', 'shop_id', 'indexpic', 'title', 'brief','course_type', 'create_time as time']);
                $content->type = 'course';
                break;
            default:
                $content = Content::where(['hashid'=>request('content_id'),'type'=>request('content_type')])->firstOrFail(['hashid','shop_id','indexpic','type','title','brief','create_time as time']);
                $content->content_id = $content->hashid;
                $content->time = (request('content_type')=='live')?$content->alive->start_time:'';
                break;
        }
        $indexpic = hg_unserialize_image_link($content->indexpic);
        $content->indexpic = $indexpic['host'].$indexpic['file'];
        $content->shop_title = Shop::where('hashid', $this->shop['id'])->value('title');
        $content->member_id = $this->member['id'];
        $content->member_name = $this->member['nick_name'];
        $content->member_avatar = Member::where(['uid'=>$this->member['id']])->value('avatar') ? : '';
        $content->time = hg_format_date($content->time);
        $content->qrcode = $this->makeQrcode($content);

        return $this->output($content);
    }

    /**
     * 内容二维码
     * @param $content
     * @return mixed
     */
    private function makeQrcode($content){
        $qrcode = $this->createQrcode($content);

        $file_name = md5($qrcode).'.png';
        $cos_path = config('qcloud.folder').'/card/qrcode/'.$file_name;
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$qrcode,$cos_path,null,null,'insertOnly');
        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        @unlink($qrcode);
        return $data['data']['source_url'];
    }

    /**
     * 生成支付卡片
     * @return \Illuminate\Http\JsonResponse
     */
    public function payCardMark()
    {
        $this->validateWithAttribute([
            'title' => 'required|string',
            'indexpic' => 'required|string',
            'price' => 'required|string',
            'h5_url' => 'required|string',
        ], [
            'title' => '标题',
            'indeximg' => '索引图',
            'price' => '价格',
            'h5_url' => 'h5链接',
        ]);
        $title = request('title');
        $indexpic = request('indexpic');
        $price = request('price');
        $h5_url = request('h5_url');
        $price = $price;
        $key = md5('pay:card_'.$title . '__' . $indexpic . '__' . $price . '__' . $h5_url);
        if ($image_cache = Cache::get($key)) {
            $image_url = $image_cache;
        } else {
            $background = resource_path('material/card/pay/') . 'pay_card_background.png';
            $qrcode_title = '扫码付款';
            $qrcode_des = '保存后，通过微信扫一扫完成购买';
            $img = Image::make($background);
            $indexpic_resize = $this->pay_image_resize($indexpic, 240, 150);
            $img->insert($indexpic_resize, "top-left", 48, 72);
            unlink($indexpic_resize);

            $titles = $this->splitText($title, 30);
            for ($i = 0; $i < count($titles); $i++) {
                $left = 318;
                $top = 75 + $i * 48;
                $img->text($titles[$i], $left, $top, function ($font) {
                    $font->file(resource_path('material/card/ttf/simhei.ttf'));
                    $font->size(42);
                    $font->color('#333');       //
                    $font->align("left");    //left, right and center Default: left
                    $font->valign("top");   //top bottom and middle Default: bottom
                    $font->angle("0");       //倾斜
                });
            }


            $symbol_rmb = resource_path('material/card/pay/') . 'symbol_rmb.png';
            $img->insert($symbol_rmb, "top-left", 318, 190);

            $img->text($price, 345, 190, function ($font) {
                $font->file(resource_path('material/card/ttf/simhei.ttf'));
                $font->size(36);
                $font->color('#DC3B38');       //
                $font->align("left");    //left, right and center Default: left
                $font->valign("top");   //top bottom and middle Default: bottom
                $font->angle("0");       //倾斜
            });


            $qrcode_path = resource_path('material/card/pay/' . uniqid() . '.png');
            QrCode::format('png')->size(375)->margin(0)->generate($h5_url, $qrcode_path);
            $img->insert($qrcode_path, "top-center", 0, 399);
            unlink($qrcode_path);

            $img->text($qrcode_title, 415, 822, function ($font) {
                $font->file(resource_path('material/card/ttf/simhei.ttf'));
                $font->size(48);
                $font->color('#333');       //
                $font->align("left");    //left, right and center Default: left
                $font->valign("top");   //top bottom and middle Default: bottom
                $font->angle("0");       //倾斜
            });

            $img->text($qrcode_des, 240, 900, function ($font) {
                $font->file(resource_path('material/card/ttf/simhei.ttf'));
                $font->size(36);
                $font->color('#999');       //
                $font->align("left");    //left, right and center Default: left
                $font->valign("top");   //top bottom and middle Default: bottom
                $font->angle("0");       //倾斜
            });
            $image_path = resource_path('material/card/pay/' . uniqid() . '.png');
            $img->save($image_path);
            $image_url = $this->payCardUploadCos($image_path);
            Cache::put($key, $image_url, 30);
        }
        if($image_url){
            $link_array = parse_url($image_url);
            $scheme = $link_array['scheme'];
            $host = $link_array['host'];
            $image_host = $scheme . '://' . $host;
            $image_url = str_replace($image_host, IMAGE_HOST, $image_url);
        }
        return $this->output(['image_url' => $image_url]);
    }

    private function splitText($text, $split_length)
    {
        $text_array = [];
        $len = mb_strwidth($text);
        if ($len > $split_length) {
            $num = ceil($len / $split_length);
            for ($i = 0; $i < $num; $i++) {
                $start = $i * $split_length;
                $text_array[$i] = mb_strimwidth($text, $start, $split_length, '', 'utf-8');
            }
        } else {
            array_push($text_array, $text);
        }
        return $text_array;
    }

    private function pay_image_resize($url, $new_width, $new_height)
    {
        $imgname = resource_path('material/card/pay/') . uniqid() . '.jpg';
        $file = $url;
        list($width, $height) = getimagesize($file); //获取原图尺寸
        $source_ratio  = $height / $width;
        $target_ratio  = $new_height / $new_width;
        $file_dimensions = getimagesize($file); //获取图像信息
        $file_type = strtolower($file_dimensions['mime']);  //获取图像类型
        $src_im = '';
        switch ($file_type) {
            case 'image/png':
                $src_im = imagecreatefrompng($file);
                break;
            case 'image/gif':
                $src_im = imagecreatefromgif($file);
                break;
            case 'image/jpeg':
            case 'image/pjpeg':
                $src_im = imagecreatefromjpeg($file);
                break;
            default:
                $this->error('WRONG_FILE_TYPE');
        }
        // 裁剪
        if ($source_ratio > $target_ratio) {
            $cropped_width = $width;
            $cropped_height = $width * $target_ratio;
            $source_x = 0;
            $source_y = ($height - $cropped_height) / 2;
        } else if ($source_ratio < $target_ratio) {
            $cropped_width = $height / $target_ratio;
            $cropped_height = $height;
            $source_x = ($width - $cropped_width) / 2;
            $source_y = 0;
        } else {
            $cropped_width = $width;
            $cropped_height = $height;
            $source_x = 0;
            $source_y = 0;
        }
        $cropped_im = imagecreatetruecolor($cropped_width, $cropped_height);
        //图片裁剪
        imagecopy($cropped_im, $src_im, 0, 0, $source_x, $source_y, $cropped_width, $cropped_height);
        $dst_im = imagecreatetruecolor($new_width, $new_height);
        //图片缩放
        imagecopyresized($dst_im, $cropped_im, 0, 0, 0, 0, $new_width, $new_height, $cropped_width, $cropped_height);
        imagejpeg($dst_im, $imgname); //输出压缩后的图片
        imagedestroy($dst_im);
        imagedestroy($cropped_im);
        imagedestroy($src_im);

        $w = $new_width;
        $h = $new_height; // original size
        $dest_path = resource_path('material/card/pay/') . uniqid() . '.png';
        $src = imagecreatefromstring(file_get_contents($imgname));
        $newpic = imagecreatetruecolor($w, $h);
        imagealphablending($newpic, false);
        for ($x = 0; $x < $w; $x++)
            for ($y = 0; $y < $h; $y++) {
                $c = @imagecolorat($src, $x, $y);
                imagesetpixel($newpic, $x, $y, $c);
            }
        imagesavealpha($newpic, true);
        imagepng($newpic, $dest_path);
        imagedestroy($newpic);
        imagedestroy($src);
        unlink($imgname);
        return $dest_path;
    }

    /**
     * 支付卡片上传到cos
     * @param $content
     * @return mixed
     */
    private function payCardUploadCos($path){
        $file_name = 'pay_card_'.md5($path).'.jpg';
        $cos_path = config('qcloud.folder').'/card/pay/'.$file_name;
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$path,$cos_path,null,null,'insertOnly');
        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        unlink($path);
        return $data['data']['source_url'];
    }

    /**
     * 推广店铺卡片
     * @return \Illuminate\Http\JsonResponse
     */
    public function promotionShopMake(){
        $this->validateWithAttribute([
            'type'  => 'required|in:url,base64',
        ],[
            'template_id'   => '类型',
        ]);
        $type = request('type');
        $shop_id = $this->shop['id'];
        $promotion_id = request('promotion_id');
        $member_id = $promotion_id ? $promotion_id : $this->member['id'];
        $promoter = hg_check_promotion($member_id, $shop_id);
        if(!$promoter){
            $this->error('member-not-promoter');
        }
        $shop = Shop::where(['hashid'=>$shop_id])->firstOrFail();
        $member = Member::where(['shop_id'=>$shop_id, 'uid'=>$member_id])->firstOrFail();
        $link = '/'.$shop_id.'/#/?promoter_id='.$promoter->promotion_id;
        if($type=='base64'){
            $key = md5('promotion:shop:base64:' . $shop_id . '__' . $member_id);
            if ($image_cache = Cache::get($key)) {
                $image_base64 = $image_cache;
            } else {
                $image = $this->promotionCardMake($shop, $member, '发现了一个好店铺，分享给你', $link);
                $image_base64 = $this->image2base64($image);
                Cache::put($key, $image_base64, 10);
                if($image && file_exists($image)){
                    unlink($image);
                }
            }
            return $this->output(['image_base64' => $image_base64]);
        } else {
            $key = md5('promotion:shop:image:' . $shop_id . '__' . $member_id);
            if ($image_cache = Cache::get($key)) {
                $image_url = $image_cache;
            } else {
                $image = $this->promotionCardMake($shop, $member, '发现了一个好店铺，分享给你', $link);
                $image_url = $this->uploadImage2Cos('promotion_shop_', 'promotion', $image);
                Cache::put($key, $image_url, 30);
            }
            return $this->output(['image_url' => $image_url]);
        }
    }

    /**
     * 推广邀请卡片
     * @return \Illuminate\Http\JsonResponse
     */
    public function promotionInviteMake(){
        $this->validateWithAttribute([
            'type'  => 'required|in:url,base64',
        ],[
            'template_id'   => '类型',
        ]);
        $type = request('type');
        $shop_id = $this->shop['id'];
        $shop = Shop::where(['hashid'=>$shop_id])->firstOrFail();
        $promotion_id = request('promotion_id');
        $member_id = $promotion_id ? $promotion_id : $this->member['id'];
        $promoter = hg_check_promotion($member_id, $shop_id);
        if(!$promoter){
            $this->error('member-not-promoter');
        }
        $member = Member::where(['shop_id'=>$shop_id, 'uid'=>$member_id])->firstOrFail();
        $link = '/'.$shop_id.'/#/popularize/plan/center?promoter_id='.$promoter->promotion_id;
        if($type=='base64'){
            $key = md5('promotion:invite:card:base64:' . $shop_id . '__' . $member_id);
            if ($image_cache = Cache::get($key)) {
                $image_base64 = $image_cache;
            } else {
                $image = $this->promotionCardMake($shop, $member, '邀你一起加入，推广赚佣金', $link);
                $image_base64 = $this->image2base64($image);
                Cache::put($key, $image_base64, 10);
                if($image && file_exists($image)){
                    unlink($image);
                }
            }
            return $this->output(['image_base64' => $image_base64]);
        }else {
            $key = md5('promotion:invite:card:image:' . $shop_id . '__' . $member_id);
            if ($image_cache = Cache::get($key)) {
                $image_url = $image_cache;
            } else {
                $image = $this->promotionCardMake($shop, $member, '邀你一起加入，推广赚佣金', $link);
                $image_url = $this->uploadImage2Cos('promotion_invite_', 'promotion', $image);
                Cache::put($key, $image_url, 30);
            }
            return $this->output(['image_url' => $image_url]);
        }
    }

    private function promotionCardMake($shop, $member, $text, $link)
    {
        $domain =  H5_DOMAIN;
        if(env('APP_ENV') == 'production'){
            $domain = 'https://' . $shop->h5_host;
        }
        $qr_code_url = $domain . $link;
        $avatar_url = $member->avatar;
        $nick_name = $member->nick_name;
        $title = $shop->title;

        $background = resource_path('material/card/promotion/') . 'background.png';
        $image = Image::make($background);
        if ($avatar_url) {
            $client = new Client();
            $avatar_data = $client->request('get', $avatar_url,['verify' => false])->getBody()->getContents();
            $avatar = resource_path('material/card/image/') . md5($avatar_url.str_random(6));
            file_put_contents($avatar, $avatar_data);
            $avatar_image = $this->image_circle($this->image_resize($avatar, 100, 100));
            file_exists($avatar) && @unlink($avatar);
        } else {
            $avatar_image = Image::canvas(100, 100);
            $avatar_image->circle(100, 50, 50, function ($draw) {
                $draw->background('#DDDCD8');
            });
        }
        $image->insert($avatar_image, "top-center", 0, 102);
        isset($avatar_image) && file_exists($avatar_image) && @unlink($avatar_image);

        $x = $this->image_text_center(670, 840, $nick_name, resource_path('material/card/ttf/simhei.ttf'), 28)[0];
        $image->text($nick_name, $x, 227, function ($font) {
            $font->file(resource_path('material/card/ttf/simhei.ttf'));
            $font->size(28);
            $font->color('#333');       //
            $font->align("left");    //left, right and center Default: left
            $font->valign("top");   //top bottom and middle Default: bottom
            $font->angle("0");       //倾斜
        });

        $x = $this->image_text_center(670, 840, $text, resource_path('material/card/ttf/simhei.ttf'), 24)[0];
        $image->text($text, $x, 274, function ($font) {
            $font->file(resource_path('material/card/ttf/simhei.ttf'));
            $font->size(24);
            $font->color('#999');       //
            $font->align("left");    //left, right and center Default: left
            $font->valign("top");   //top bottom and middle Default: bottom
            $font->angle("0");       //倾斜
        });

        $x = $this->image_text_center(670, 840, $title, resource_path('material/card/ttf/simhei.ttf'), 32)[0];
        $image->text($title, $x, 419, function ($font) {
            $font->file(resource_path('material/card/ttf/simhei.ttf'));
            $font->size(32);
            $font->color('#333');       //
            $font->align("left");    //left, right and center Default: left
            $font->valign("top");   //top bottom and middle Default: bottom
            $font->angle("0");       //倾斜
        });

        $qrcode_path = resource_path('material/card/promotion/' . uniqid() . '.png');
        QrCode::format('png')->size(240)->margin(0)->generate($qr_code_url, $qrcode_path);
        $image->insert($qrcode_path, "top-center", 0, 480);
        unlink($qrcode_path);

        $image_path = resource_path('material/card/promotion/' . uniqid() . '.png');
        $image->save($image_path);
        return $image_path;
    }

    function image_text_center($width, $height, $text, $font, $size, $angle = 45)
    {
        $xi = $width;
        $yi = $height;

        $box = imagettfbbox($size, $angle, $font, $text);

        $xr = abs(max($box[2], $box[4]));
        $yr = abs(max($box[5], $box[7]));

        $x = intval(($xi - $xr) / 2);
        $y = intval(($yi + $yr) / 2);
        return array($x, $y);
    }


    private function uploadImage2Cos($name, $dir, $path){
        $file_name = $name.md5($path).'.jpg';
        $cos_path = config('qcloud.folder').'/card/'.$dir.'/'.$file_name;
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'),$path,$cos_path,null,null,'insertOnly');
        $data['code'] && $this->errorWithText($data['code'],$data['message']);
        unlink($path);
        return $data['data']['source_url'];
    }

    private function image2base64($file){
        $img_base64 = '';
        if (file_exists($file)) {
            $img_info = getimagesize($file); // 取得图片的大小，类型等
            $fp = fopen($file, "r"); // 图片是否可读权限
            if ($fp) {
                $filesize = filesize($file);
                $img_content = fread($fp, $filesize);
                $file_content = chunk_split(base64_encode($img_content)); // base64编码
                switch ($img_info[2]) {           //判读图片类型
                    case 1: $img_type = "gif";break;
                    case 2: $img_type = "jpg";break;
                    case 3: $img_type = "png";break;
                }
                $img_base64 = 'data:image/' . $img_type . ';base64,' . $file_content;//合成图片的base64编码
            }
            fclose($fp);
        }
        return $img_base64;
    }

}