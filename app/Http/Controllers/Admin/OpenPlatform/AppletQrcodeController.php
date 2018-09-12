<?php

namespace App\Http\Controllers\Admin\OpenPlatform;

use App\Http\Controllers\Admin\BaseController;
use App\Http\Controllers\Admin\OpenPlatform\Publics\QcloudController as qcloud;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;


use App\Events\ErrorHandle;



use App\Models\Course;
use App\Models\Content;
use App\Models\Community;
use App\Models\Column;
use App\Models\MemberCard;
use App\Models\OpenPlatformApplet;
use App\Models\Shop;
use App\Models\OfflineCourse;
use App\Models\Type;
use App\Models\ShortLink;

class AppletQrcodeController extends BaseController
{
    use CoreTrait;

    const API_GET_WXACODE = 'https://api.weixin.qq.com/wxa/getwxacode';
    const API_GET_WXACODE_UNLIMIT = 'http://api.weixin.qq.com/wxa/getwxacodeunlimit';
    const API_CREATE_QRCODE = 'https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode';

    protected $type = 'applet';

    public function __construct()
    {
        parent::__construct();
    }

    public function getRoundQrcode()
    {
        $params = $this->validateCode();
        $stream = $this->getAppCode($params['path'], $params['width']);
        return $stream;
    }

    // 小程序码

    private function validateCode()
    {
        $this->validateWithAttribute([
            'scene' => 'max:32',
            'path'  => 'max:128',
            'width' => 'numeric',
        ], [
            'scene' => '进入小程序的显示页',
            'path'  => '进入小程序的显示页',
            'width' => '二维码宽度',
        ]);
        return [
            'scene' => trim(request('scene')) ?: 1,
            'path'  => trim(request('path')) ?: '',
            'width' => intval(request('width')) ?: 160,
        ];
    }

    // 无限制小程序码

    private function getAppCode($path, $width, $autoColor = false, $lineColor = ['r' => 0, 'g' => 0, 'b' => 0])
    {
        $params = [
            'path'       => $path,
            'width'      => $width,
            'auto_color' => $autoColor,
            'line_color' => $lineColor,
        ];
        $authorizationData = $this->getAuthorizerAccessToken();
        $token = $authorizationData['authorizer_access_token'];
        $appid = $authorizationData['open_platform']->appid;
        $redis_ret = $this->getFromRedis($params, $appid);
        if (!$redis_ret || request('flash')) {
            $url = self::API_GET_WXACODE . '?access_token=' . $token;
            return $this->getStream($url, $params, $appid, 'limit');
        }
        return $redis_ret;
    }

    // 小程序二维码

    private function getFromRedis($params, $appid)
    {
        return Redis::hget('openflatform:appcode:' . $appid, json_encode($params));
    }

    private function getStream($url, $params, $appid, $type)
    {
        $stream = (new HttpClient())
            ->request('POST', $url, ['json' => $params])
            ->getBody()
            ->getContents();
        $name = md5($appid . $type);
        $name = $name.'.jpg';
        $ret = ['qrcode' => (new qcloud)->uploadImg($name, $stream)];
        Redis::hset('openflatform:appcode:' . $appid, json_encode($params), json_encode($ret));
        return $ret;
    }


    private function requestAppletCode($url, $payload){
        $response = (new HttpClient())->request('POST', $url, ['json' => $payload]);
        if (!$response->hasHeader('Content-Type')) {
            $p = json_encode($payload);
            hg_fpc('内容预览小程序码请求失败:'. $url .':'. $p .':'. $response->getBody());
            return $this->error('applet_content_priview_error');
        };

        if(!in_array('image/jpeg', $response->getHeader('Content-Type'))) {
            $p = json_encode($payload);
            hg_fpc('内容预览小程序码请求失败:' . $url . ':'. $p .':'. $response->getBody());
            $responseJson = json_decode($response->getBody());
            if(property_exists($responseJson, 'errcode') && $responseJson->errcode == 41030) {
                return $this->error('applet_content_priview_error_at_invalid_page');
            }
            return $this->error('applet_content_priview_error');
        }

        $image = $response
                ->getBody()
                ->getContents();
        
        return $image;
    }

    private function uploadToQcloud($image, $name) {
        $ret = (new qcloud)->uploadImg($name, $image);
        return $ret;
    }

    private function cacheContentAppletCodeUrl($shopid, $field, $value) {
        $key = $this->appletContentpreviewCacheKey($shopid);
        Redis::hset($key, $field, $value);
    }

    private function appletContentpreviewCacheKey($shopid) {
        return 'openflatform:contentcode:'.$shopid;
    }

    private function contentpreviewCacheField($type, $id, $join=':') {
        return $type.$join.$id;
    }

    private function getContentAppletCodeUrl($shopid, $type, $id) {
        $url = $this->getContentAppletCodeUrlFormCache($shopid, $type, $id);
        $url && hg_fpc('小程序页面二维码from cache '.$shopid.$type.':'.$id.':'.$url);
        if(!$url) {
            $url = $this->getContentAppletCodeUrlFromWxApi($shopid, $type, $id);
            $field = $this->contentpreviewCacheField($type, $id);
            $this->cacheContentAppletCodeUrl($shopid, $field, $url);
            hg_fpc('小程序页面二维码from api '.$shopid.$type.':'.$id.':'.$url);
        }
        return $url;
    }

    private function getContentAppletCodeUrlFormCache($shopid, $type, $id) {
        $key = $this->appletContentpreviewCacheKey($shopid);
        $field = $this->contentpreviewCacheField($type, $id);
        return Redis::hget($key, $field);
    }

    private function getContentAppletCodeUrlFromWxApi($shopid, $type, $id, $autoColor = false, $lineColor = ['r' => 0, 'g' => 0, 'b' => 0]){
        $page_scene = $this->contentPageParams($type,$id);
        $middle_page_scene = $this->contentMiddlePageParams($page_scene);
        $payload = [
            'page' => $middle_page_scene['page'],
            'scene' => $middle_page_scene['scene'], 
            'auto_color' => $autoColor,
            'line_color' => $lineColor
        ];
        $authorizationData = $this->getAuthorizerAccessToken();
        $token = $authorizationData['authorizer_access_token'];
        $appid = $authorizationData['open_platform']->appid;
        $url = self::API_GET_WXACODE_UNLIMIT.'?access_token=' . $token;
        $image = $this->requestAppletCode($url, $payload);
        $name = $this->contentpreviewCacheField($type, $id,'-');
        $name = 'preview-'.$shopid.'-'.$name.'.jpg';
        $url = $this->uploadToQcloud($image, $name);
        return $url;
    }

    private function contentPageParams($type, $id) {
        $page = '';
        $scene = '';
        $path = '';
        switch ($type) {
            case 'article':
                $page = 'pages/brief/brief';
                $scene = 'article'.':'.$id;
                $path = '/'. $page .'?cid='. $id . '&' . 'type='. $type; //小程序路径 http://help.duanshu.com/web/#/2?page_id=20
                break;
            case 'audio':
                $page = 'pages/brief/brief';
                $scene = 'audio'.':'.$id;
                $path = '/' . $page . '?cid=' . $id . '&' . 'type=' . $type;
                break;
            case 'video':
                $page = 'pages/brief/brief';
                $scene = 'video'.':'.$id;
                $path = '/' . $page . '?cid=' . $id . '&' . 'type=' . $type;
                break;
            case 'column':
                $page = 'pages/column/column';
                $scene = $id;
                $path = '/' . $page . '?cid=' . $id;
                break;
            case 'course':
                $course = Course::where(['shop_id'=>$this->shop['id'],'hashid'=>$id])->first();
                $page = 'pages/bricourse/bricourse';
                $scene = $course->course_type.':'.$id;
                $path = '/'. $page .'?cid='. $id . '&' .'type='. $course->course_type;
                break;
            case 'community':
                $page = 'pages/communityjoin/communityjoin';
                $scene = $id;
                $path = '/' . $page . '?cid=' . $id;
                break;
            case 'member_card':
                $page = 'pages/membershipCardDetail/membershipCardDetail';
                $scene = $id;
                $path = '/' . $page . '?card_id=' . $id;
                break;
            case 'gift_code':
                $page = 'pages/giftcode/giftcode';
                $scene = $id;
                $path = '/pages/invitecode/invitecode';
                break;
            case 'offlinecourse':
                $page = 'pages/offlinecourse/offlinecourse';
                $scene = $id;
                $path = '/' . $page . '?cid=' . $id;
                break;
            case 'content_class':
                $page = 'pages/utility/utility';
                $scene = $id;
                $path = '/' . $page . '?id=' . $id;
                break;
            case 'qunfazengsong':
                $page = 'pages/receiveGivePhoneVerify/receiveGivePhoneVerify';
                $scene = '';
                $path = '/' . $page;
                break;
            default:
                break;
        }

        return ['page'=>$page, 'scene'=>$scene,'path'=>$path];
    }


    public function contentMiddlePageParams($params){ //内容预览中间跳转页
        $shortLink = $this->createShortLink($params);
        return ['page'=>'pages/contentpreview/contentpreview','scene'=>$shortLink->key];
    }

    public function createShortLink($params){
        $page = $params['page'];
        $scene = $params['scene'];
        $now = date_create(null, timezone_open('UTC'));
        $now_str = $now->format('Y-m-d H:i:s');
        $m = ['page'=>$page,'scene'=>$scene];
        $shortLink = new ShortLink();
        $shortLink->setRawAttributes(['data'=>json_encode($m),'update_time'=>$now_str]);
        $shortLink->create();
        return $shortLink;
    }

    public function getContentAppletPreview() {
        $open_platform = OpenPlatformApplet::where('shop_id', $this->shop['id'])->first();
        $this->shopInstance = Shop::where('hashid',$this->shop['id'])->first();
        if (is_null($open_platform)) {
            return $this->error('no_authorizer');
        }
        if(!$this->shopInstance->isAppletReleased()) {
            return $this->error('no_applet_release');
        }
        $this->validateContentAppletPreview();
        DB::beginTransaction();
        try{
            $url = $this->getContentAppletCodeUrl($this->shop['id'], request('content_type'), request('content_id', ''));
            DB::commit();
        }catch(\Exception $e){
            if (!$e instanceof HttpResponseException) {
                event(new ErrorHandle($e));
            }
            DB::rollBack();
            throw $e;
        }
        $params = $this->contentPageParams(request('content_type'), request('content_id',''));
        $path = $params['path'];
        return $this->output(['pr_code'=>$url,'path'=>$path]);
    }

    private function validateContentAppletPreview() {
        $contentTypes = ['article','audio','video','column','course','community','member_card', 'gift_code', 'offlinecourse', 'content_class','qunfazengsong'];
        $this->validateWithAttribute([
            'content_type' => ['required',Rule::in($contentTypes)],
            'content_id'  => ''
        ], [
            'content_type' => '内容类型',
            'content_id'  => '内容id'
        ]);
        $shopId = $this->shop['id'];
        $contentId = request('content_id','');
        switch (request('content_type')) {
            case 'article':
            case 'audio':
            case 'video':
                Content::where(['shop_id'=>$shopId,'hashid'=>$contentId])->firstOrFail();
                break;
            case 'course':
                Course::where(['shop_id'=>$shopId,'hashid'=>$contentId])->firstOrFail();
                break;
            case 'column':
                Column::where(['shop_id'=>$shopId,'hashid'=>$contentId])->firstOrFail();
                break;
            case 'community':
                Community::where(['shop_id'=>$shopId,'hashid'=>$contentId])->firstOrFail();
                break;
            case 'member_card':
                MemberCard::where(['shop_id'=>$shopId,'hashid'=>$contentId])->firstOrFail();
                break;
            case 'offlinecourse':
                OfflineCourse::where(['shop_id' => $this->shopInstance->id, 'id' => $contentId])->firstOrFail();
                break;
            case 'content_class':
                Type::where(['shop_id' => $shopId, 'id' => $contentId])->firstOrFail();
                break; 
            default:
                break;
        }
    }

    public function getUnlimitQrcode()
    {
        $params = $this->validateCode();
        $is_refresh = request('flash');
        return $this->getAppCodeUnlimit($params['scene'], $params['width'], $is_refresh);
    }

    /**
     * 刷新二维码
     * @return array|void
     */
    public function refreshUnlimitQrcode(){
        $params = $this->validateCode();
        $shop_id = $this->shop['id'];
        $key = 'weapp:refresh:unlimit:qrcode:'.$shop_id;
        if(Redis::get($key)){
            return $this->error('refresh-too-busy');
        }
        //5分钟禁止多次刷新
        $time = 5 * 60;
        Redis::setex($key, $time, 1);
        return $this->getAppCodeUnlimit($params['scene'], $params['width'], true);
    }

    // 获取图片流

    private function getAppCodeUnlimit($scene, $width, $isRefresh, $autoColor = false, $lineColor = ['r' => 0, 'g' => 0, 'b' => 0])
    {
        $params = [
            'scene'      => $scene,
            'width'      => $width,
            'auto_color' => $autoColor,
            'line_color' => $lineColor,
        ];
        request('shop_id') && $this->shop['id'] = request('shop_id');
        $authorizationData = $this->getAuthorizerAccessToken();
        $token = $authorizationData['authorizer_access_token'];
        $appid = $authorizationData['open_platform']->appid;
        $redis_ret = $this->getFromRedis($params, $appid);
        if (!$redis_ret || $isRefresh) {
            $url = self::API_GET_WXACODE_UNLIMIT . '?access_token=' . $token;
            return $this->getStream($url, $params, $appid, 'unlimit');
        }
        return $redis_ret;
    }

    public function getSquareQrcode()
    {
        $params = $this->validateCode();
        $stream = $this->createQRCode($params['path'], $params['width']);
        return $stream;
    }

    private function createQRCode($path, $width)
    {
        $params = compact('path', 'width');
        $authorizationData = $this->getAuthorizerAccessToken();
        $token = $authorizationData['authorizer_access_token'];
        $appid = $authorizationData['open_platform']->appid;
        $redis_ret = $this->getFromRedis($params, $appid);
        if (!$redis_ret || request('flash')) {
            $url = self::API_CREATE_QRCODE . '?access_token=' . $token;
            return $this->getStream($url, $params, $appid, 'qrcode');
        }
        return $redis_ret;
    }
}
