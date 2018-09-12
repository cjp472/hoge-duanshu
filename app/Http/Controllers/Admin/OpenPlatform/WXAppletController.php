<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/8/8
 * Time: 下午2:02
 */

namespace App\Http\Controllers\Admin\OpenPlatform;


use App\Events\SystemEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Http\Controllers\Admin\OpenPlatform\Publics\QcloudController as qcloud;
use App\Models\AppletCommit;
use App\Models\AppletRelease;
use App\Models\AppletSubmitAudit;
use App\Models\AppletUpgrade;
use App\Models\Manage\AppletTemplate;
use App\Models\OpenPlatformApplet;
use App\Models\Shop;
use App\Models\ShopColor;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;

class WXAppletController extends BaseController
{
    use CoreTrait;

    protected $type = 'applet';

    public function checkBind()
    {
        $open_platform_applet = OpenPlatformApplet::where('shop_id', $this->shop['id'])->first();
        if ($open_platform_applet) {
            $app_commit = AppletCommit::where([
                'shop_id'=>$this->shop['id'],
                'appid'=>$open_platform_applet->appid
            ])->first();
            if(!request('is_direct') && !$app_commit && !$open_platform_applet->is_commit){
                $this->error('no_authorizer');
            }
            $authorizer_info = $this->getAuthorizerInfo($open_platform_applet->appid);
            $authorizer_info['is_commit'] = intval($open_platform_applet->is_commit);
            try {
                $authorizer_info['category'] = $this->handleCategory($this->getCategory());
            }catch (HttpResponseException $httpResponseException){
                $response = json_decode($httpResponseException->getResponse()->getContent(),1);
                $return = array_merge($response,['name'=>isset($authorizer_info['authorizer_info']['nick_name']) ? $authorizer_info['authorizer_info']['nick_name']: '']);
                return $this->output($return);
            }
            return $this->output($authorizer_info);
        }
        $this->error('no_authorizer');
    }

    // 检查用户是否发布小程序

    private function handleCategory($category_list)
    {
        $category = [];
        if ($category_list) {
            foreach ($category_list as $vo) {
                $category[] = $vo['first_class'] . '-' . $vo['second_class'];
            }
        }
        return $category;
    }

    private function getCategory($authorizer_access_token = '')
    {
        $authorizer_access_token = $authorizer_access_token ?: $this->getAuthorizerAccessToken()['authorizer_access_token'];
        $url = config('define.open_platform.wx_applet.api.get_category')
            . '?access_token=' . $authorizer_access_token;
        return $this->curl_trait('GET', $url)['category_list'];
    }

    public function checkSubmitAudit()
    {
        $open_platform_applet = OpenPlatformApplet::where('shop_id', $this->shop['id'])->first();
        if ($open_platform_applet) {
            $where = ['shop_id' => $this->shop['id'], 'appid' => $open_platform_applet->appid];
            $submit_audit = AppletSubmitAudit::where($where)->orderBy('create_time', 'desc')->first();
            if ($submit_audit && intval($submit_audit->is_release)) {
                $this->error('no_applet_submitaudit');
            }
            return $submit_audit
                ? $this->handleSubmitAudit($submit_audit)
                : $this->error('no_applet_submitaudit');
        }
        $this->error('no_authorizer');
    }

    //用户生成体验版数据

    private function handleSubmitAudit($submit_audit)
    {
        $submit_audit->status = intval($submit_audit->status);
        $submit_audit->create_time = $submit_audit->create_time ? hg_format_date($submit_audit->create_time) : '';
        $submit_audit->audit_time = $submit_audit->audit_time ? hg_format_date($submit_audit->audit_time) : '';
        $submit_audit->status_name = $submit_audit->status ? (($submit_audit->status === 2) ? '审核中' : '审核失败') : '审核成功';
        $submit_audit->item_list = $submit_audit->item_list ? unserialize($submit_audit->item_list) : [];
        $submit_audit->category = $submit_audit->category ? unserialize($submit_audit->category) : [];
        $submit_audit->id = intval($submit_audit->id);
        $submit_audit->is_release = intval($submit_audit->is_release);
        $submit_audit->button_display = ($submit_audit->status === 0) ? 0 : 1;
        return $this->output($submit_audit);
    }

    public function checkRelease()
    {
        $open_platform_applet = OpenPlatformApplet::where('shop_id', $this->shop['id'])->first();
        if ($open_platform_applet) {
            $where = ['shop_id' => $this->shop['id'], 'appid' => $open_platform_applet->appid];
            $release = AppletRelease::where($where)->orderBy('release_time',
                'desc')->first();
            return ($release && $release->release_time)
                ? $this->handleRelease($release)
                : $this->output(['is_release' => 0, 'message' => '该店铺未发布小程序上线']);
        }
        $this->error('no_authorizer');
    }

    private function handleRelease($release)
    {
        $release->create_time = $release->create_time ? hg_format_date($release->create_time) : '';
        $release->release_time = $release->release_time ? hg_format_date($release->release_time) : '';
        $release->category = $release->category ? unserialize($release->category) : [];
        $release->id = intval($release->id);
        $release->sid = intval($release->sid);
        $release->is_release = 1;
        return $this->output($release);
    }

    //修改服务器地址

    public function checkCommit()
    {
        $open_platform_applet = OpenPlatformApplet::where('shop_id', $this->shop['id'])->first();
        if ($open_platform_applet) {
            $where = ['shop_id' => $this->shop['id'], 'appid' => $open_platform_applet->appid];
            $applet_commit = AppletCommit::where($where)->orderBy('create_time', 'desc')->first();
            return $applet_commit
                ? $this->handleCommit($applet_commit)
                : $this->error('no_applet_commit');
        }
        $this->error('no_authorizer');
    }

    //绑定微信用户为小程序体验者

    private function handleCommit($applet_commit)
    {
        $applet_commit->create_time = $applet_commit->create_time ? hg_format_date($applet_commit->create_time) : '';
        $applet_commit->category = $applet_commit->category ? unserialize($applet_commit->category) : [];
        $applet_commit->qrcode = $this->getTemporaryQrcode();
        $applet_commit->value = $applet_commit->value ? unserialize($applet_commit->value) : [];
        $commit_status = Cache::get('applet:commit:status') ? 1 : 0;
        if($commit_status){
            $applet_commit->commit_status = $commit_status;
            Cache::forget('applet:commit:status');
        }
        return $this->output($applet_commit);
    }

    //解除绑定微信用户为小程序体验证

    public function getTemporaryQrcode()
    {
        $url = config('define.open_platform.wx_applet.api.get_qrcode')
            . '?access_token=' . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        $stream = (new HttpClient())->request('GET', $url)->getBody()->getContents();
        $name = md5($this->shop['id'] . 'temporaryQrcode');
        return (new qcloud)->uploadImg($name, $stream).'?'.time();
    }

    //为授权的小程序帐号上传小程序代码

    public function modifyDomain()
    {
        $action = request('action') ?: 'add';
        $authorizationData = $this->getAuthorizerAccessToken();
        $authorizer_access_token = $authorizationData['authorizer_access_token'];
        $open_platform_applet = $authorizationData['open_platform'];
        if (IS_DOMAIN || $action === 'get') {
            $url = config('define.open_platform.wx_applet.api.modify_domain')
                . '?access_token=' . $authorizer_access_token;
            $params = ['action' => $action];
            if ($params['action'] == 'add') {
                $params['requestdomain'] = ['https://api.duanshu.com', 'https://appletapi.duanshu.com'];
                $params['wsrequestdomain'] = [];
                $params['uploaddomain'] = ['https://api.duanshu.com', 'https://appletapi.duanshu.com'];
                $params['downloaddomain'] = [
                    'https://duanshu-1253562005.picsh.myqcloud.com',
                    'https://pimg.duanshu.com'
                ];
            }
            try {
                $res = $this->curl_trait('POST', $url, $params);
            }catch (\Exception $exception){
                $params['action'] = 'set';
                $res = $this->curl_trait('POST', $url, $params);
            }
            if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
                if ($params['action'] != 'get') {
                    $open_platform_applet->is_domain = 1;
                    $open_platform_applet->save();
                    return $this->output(['success' => 1]);
                } else {
                    return $this->output($res);
                }
            }
        }
        return $this->output(['success' => 1]);
    }


    public function bindTester()
    {
        $url = config('define.open_platform.wx_applet.api.bind_tester')
            . '?access_token=' . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        $params = ['wechatid' => request('wechatid')];
        $res = $this->curl_trait('POST', $url, $params);
        if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
            $this->output(['success' => 1]);
        }
    }

    //生成活动二维码

    public function unBindTester()
    {
        $url = config('define.open_platform.wx_applet.api.unbind_tester')
            . '?access_token=' . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        $params = ['wechatid' => request('wechatid')];
        $res = $this->curl_trait('POST', $url, $params);
        if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
            $this->output(['success' => 1]);
        }
    }

    //获取体验小程序的体验二维码

    public function commit()
    {


        $authorizationData = $this->getAuthorizerAccessToken();
        $authorizer_access_token = $authorizationData['authorizer_access_token'];
        $open_platform_applet = $authorizationData['open_platform'];

        $name = $this->handleFuncInfo(unserialize($open_platform_applet['authorizer_info'])['authorization_info']['func_info']);
        if ($name) {
            $this->error('no_func_info');
        }

        $shopInfo = $this->getShopInfo();
        if (!$shopInfo) {
            $this->error('no_data');
        }

        $category = $this->getCategory($authorizer_access_token);
        if (!$category) {
            $this->error('no_category');
        }

        $this->updateRequestDomain($authorizer_access_token);
        $authorizer_info = unserialize($open_platform_applet['authorizer_info']);
        if($authorizer_info['authorizer_info']['verify_type_info']['id'] > -1){
            $this->updateWebviewDomain($authorizer_access_token);
        }

        $db = app('db');
        //开启事务，先创建小程序提交记录，成功后commit数据，失败了rollback
        $db->beginTransaction();

        //高级版状态需要生成体验版之后才修改，故缓存中转下
//        $applet_version = Shop::where('hashid',$this->shop['id'])->value('applet_version');
//        if (Cache::get('applet:version:' . $this->shop['id']) > 0) {
//            $this->shop['applet_version'] = 'advanced';
//        } elseif (Cache::get('applet:version:' . $this->shop['id']) < 0) {
//            $this->shop['applet_version'] = 'basic';
//        } else {
//            $this->shop['applet_version'] = $applet_version;
//        }

        //体验版测试账号，15190492381,guianqiang@hoge.cn,13450228671
        if(in_array($this->shop['id'],config('define.applet_test_shop'))){
            $this->shop['applet_version'] = 'test';
        }

        $appletTemplate = $this->getAppletTemplate();
        if (!$appletTemplate) {
            $this->error('no_applet_template');
        }

        $color = $this->getAppletColor();

        $url = config('define.open_platform.wx_applet.api.commit')
            . '?access_token=' . $authorizer_access_token;
        $extJson = [
            'extAppid' => $open_platform_applet->appid,
            'ext'      => [
                'title'    => request('title') ?: $shopInfo->title,
                'brief'    => $shopInfo->brief,
                'indexpic' => $shopInfo->indexpic ? unserialize($shopInfo->indexpic) : [],
                'h5Url'    => H5_DOMAIN .'/'. $shopInfo->hashid . '/#/',
                'h5QRcode' => $this->getH5Qrcode(['url' => H5_DOMAIN . '/' . $this->shop['id'] . '/#/']),
                'shopid'   => $shopInfo->hashid,
                'appid'    => $open_platform_applet->appid,
                'applet_version'=> $this->shop['applet_version'] == 'basic' ? 'basic' : 'advanced',
                'shop'     => [
                    'title'   => request('title') ?: $shopInfo->title,
                    'brief'   => $shopInfo->brief,
                    'status'  => intval($shopInfo->status),
                    'version' => $shopInfo->version
                ],
                'environment' => getenv('APP_ENV') == 'pre' ? 'pre' : 'release'
            ]
        ];
        if ($color) {
            $handleColor = trim($color, '#');
            $extJson['ext']['shop']['color'] = $color;
            $extJson['window'] = [
                'navigationBarBackgroundColor' => $color,
            ];
            $extJson['tabBar'] = [
                'selectedColor' => $color
//                'list'          => [
//                    [
//                        "pagePath"         => "pages/index/index",
//                        "text"             => "发现",
//                        "iconPath"         => "images/tabBar/faxian.png",
//                        "selectedIconPath" => "images/tabBar/faxian_" . $handleColor . ".png"
//                    ],
//                    [
//                        "pagePath"         => "pages/subscibe/subscibe",
//                        "text"             => "订阅",
//                        "iconPath"         => "images/tabBar/dingyue.png",
//                        "selectedIconPath" => "images/tabBar/dingyue_" . $handleColor . ".png"
//                    ],
//                    [
//                        "pagePath"         => "pages/mine/index",
//                        "text"             => "我的",
//                        "iconPath"         => "images/tabBar/mine.png",
//                        "selectedIconPath" => "images/tabBar/mine_" . $handleColor . ".png"
//                    ]
//                ]
            ];
            if ($color == '#fff') {
                $extJson['window']['navigationBarTextStyle'] = 'black';
                $extJson['tabBar']['selectedColor'] = '#000';
            }else{
                $extJson['window']['navigationBarTextStyle'] = 'white';
            }
        }
        $user_commit = new AppletCommit();
        $user_commit->appid = $open_platform_applet->appid;
        $user_commit->shop_id = $shopInfo->hashid;
        $user_commit->template_id = $appletTemplate->template_id;
        $user_commit->user_version = $appletTemplate->user_version;
        $user_commit->create_time = time();
        $user_commit->category = serialize($this->handleCategory($category));
        $user_commit->save();
        // 设置小程序审核版本号
        $extJson['ext']['version'] = $user_commit->id;
        $body = [
            'template_id'  => $appletTemplate->template_id,
            'user_version' => $appletTemplate->user_version,
            'ext_json'     => json_encode($extJson, JSON_UNESCAPED_UNICODE),
        ];
        $edition = $this->shop['applet_version'] == 'basic' ? '基础版' : '高级版';
        $body['user_desc'] = '短书小程序' . $edition . $body['user_version'] . '版本，第' . $body['template_id'] . '套模板';
        $headers = ['Content-Type' => 'application/json'];
        $res = $this->curl_trait('POST', $url, '', $headers, $body);
        if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
            // 成功后commit数据
            $user_commit->value = serialize($body);
            $user_commit->save();
            $db->commit();
        } else {
            // 失败了rollback
            $db->rollback();
            $this->error('commit_error');
        }
        if ($open_platform_applet->is_commit === 0) {
            $open_platform_applet->is_commit = 1;
            request('title') && $open_platform_applet->diy_name = request('title');
            $open_platform_applet->save();
        }
        //生成体验版，修改版本信息
//        if(Cache::get('applet:version:'.$this->shop['id']) > 0 ){
//            Shop::where('hashid',$this->shop['id'])->update(['applet_version'=>'advanced']);
//            Cache::forget('applet:version:'.$this->shop['id']);
//        }elseif(Cache::get('applet:version:'.$this->shop['id']) < 0 ){
//            //小程序解绑,生成体验版后修改版本信息
//            AppletUpgrade::where('shop_id',$this->shop['id'])->delete();
//            Shop::where('hashid',$this->shop['id'])->update(['applet_version'=>'basic']);
//            Cache::forget('applet:version:'.$this->shop['id']);
//        }
        //设置缓存提交体验版状态
        Cache::forever('applet:commit:status',1);

        return $this->output(['success' => 1]);
    }

    private function updateRequestDomain($authorizer_access_token)
    {
        $url = config('define.open_platform.wx_applet.api.modify_domain')
            . '?access_token=' . $authorizer_access_token;
        $headers = ['Content-Type' => 'application/json'];
        $res = $this->curl_trait('POST', $url, '', $headers, ['action' => 'get']);
        if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
            $domain = config('define.open_platform');
            $new_request = $new_wsrequest = $new_upload = $new_download = [];
            if(is_array($res['requestdomain']) && is_array($domain['requestdomain'])){
                $new_request = array_diff($domain['requestdomain'],$res['requestdomain']);
            }
            if(is_array($res['wsrequestdomain']) && is_array($domain['wsrequestdomain'])){
                $new_wsrequest = array_diff($domain['wsrequestdomain'],$res['wsrequestdomain']);
            }
            if(is_array($res['uploaddomain']) && is_array($domain['uploaddomain'])){
                $new_upload = array_diff($domain['uploaddomain'],$res['uploaddomain']);
            }
            if(is_array($res['downloaddomain']) && is_array($domain['downloaddomain'])){
                $new_download = array_diff($domain['downloaddomain'],$res['downloaddomain']);
            }
            if($new_request || $new_wsrequest || $new_upload || $new_download){
                $this->curl_trait('POST', $url, '', $headers, [
                    'action' => 'add',
                    'requestdomain' => array_values($new_request),
                    'wsrequestdomain' => array_values($new_wsrequest),
                    'uploaddomain' => array_values($new_upload),
                    'downloaddomain' => array_values($new_download),
                ]);
            }
        }
    }
    private function updateWebviewDomain($authorizer_access_token)
    {
        $url = config('define.open_platform.wx_applet.api.web_view_domain')
            . '?access_token=' . $authorizer_access_token;
        $headers = ['Content-Type' => 'application/json'];
        $res = $this->curl_trait('POST', $url, '', $headers, ['action' => 'get']);
        if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
            $domain = config('define.open_platform');
            $new_webview = [];
            if(is_array($res['webviewdomain']) && is_array($domain['webviewdomain'])){
                $new_webview = array_diff($domain['webviewdomain'],$res['webviewdomain']);
            }
            if($new_webview){
                $this->curl_trait('POST', $url, '', $headers, [
                    'action' => 'add',
                    'webviewdomain' => array_values($new_webview),
                ]);
            }
        }
    }

    //获取授权小程序帐号的可选类目

    private function getShopInfo()
    {
        return Shop::where('hashid', $this->shop['id'])
            ->leftJoin('share', 'shop.hashid', '=', 'share.shop_id')
            ->select('shop.hashid', 'shop.title', 'shop.brief', 'shop.status', 'shop.version', 'share.indexpic','shop.applet_version')
            ->first();
    }

    private function getAppletTemplate()
    {
        return AppletTemplate::where([
            'is_display' => 1,
            'edition'    => $this->shop['applet_version']
        ])->first();
    }

    private function getAppletColor()
    {
        $color = ShopColor::where(['shop_id' => $this->shop['id'], 'shop_color.type' => 'applet'])
            ->leftJoin('color_template', 'shop_color.color_id', '=', 'color_template.id')
            ->orderBy('shop_color.create_time', 'desc')
            ->select('shop_color.*', 'color_template.color', 'color_template.class', 'color_template.title')
            ->first();
        return $color ? $color->color : '';
    }

    private function getH5Qrcode($body)
    {
        $timestamp = time();
        $signature = hg_hash_sha256([
            'timestamp'     => $timestamp,
            'access_key'    => config('sms.sign_param.key'),
            'access_secret' => config('sms.sign_param.secret'),
        ]);
        $headers = [
            'Content-Type'    => 'application/json',
            'x-api-timestamp' => $timestamp,
            'x-api-key'       => config('sms.sign_param.key'),
            'x-api-signature' => $signature,
        ];
        $url = config('define.inner_config.api.getH5QRcode');
        $response = $this->curl_trait('GET', $url, '', $headers, $body);
        //如果腾讯云返回文件已存在，这边直接返回文件路径
        if(isset($response['error']) && $response['error'] == -177){
            return IMAGE_HOST.'/'.config('qcloud.folder').'/qrcode/'.md5($body['url']).'.png';

        }
        return isset($response['response']['qrcode']) ? $response['response']['qrcode'] : '';
    }

    //获取小程序的第三方提交代码的页面配置

    public function submitAudit()
    {
        $this->validateWith(['template_id' => 'required', 'user_version' => 'required']);
        $authorizationData = $this->getAuthorizerAccessToken();
        $authorizer_access_token = $authorizationData['authorizer_access_token'];
        $open_platform_applet = $authorizationData['open_platform'];
        $app_commit = AppletCommit::where([
            'shop_id'=>$this->shop['id'],
            'appid'=>$open_platform_applet->appid
        ])->orderBy('create_time', 'desc')->first();
        if(!$app_commit){
            $this->error('no_applet_commit_version');
        }
        $submit_audit = AppletSubmitAudit::where(['shop_id' => $this->shop['id'],
            'applet_commit_id' => $app_commit->id])->first();
        if($submit_audit && $submit_audit->status == 2){
            $this->error('shop-applet-audit');
        }
        if($submit_audit && $submit_audit->status == 0){
            $this->error('applet_audit_pass');
        }
        $category = $this->getCategory($authorizer_access_token);
        if (!$category) {
            $this->error('no_category');
        }
        $url = config('define.open_platform.wx_applet.api.submit_audit')
            . '?access_token=' . $authorizer_access_token;
        $k = 0;
        $item_list = [];
        while (isset($category[$k]) && $k <= 1) {
            $item_list[] = $this->handleItem($category[$k], $k);
            $k++;
        }
        $body = ['item_list' => $item_list];
        $headers = ['Content-Type' => 'application/json'];
        $res = $this->curl_trait('POST', $url, '', $headers, $body);
        if (isset($res['auditid'])) {
            $submit_audit = new AppletSubmitAudit();
            $submit_audit->auditid = $res['auditid'];
            $submit_audit->shop_id = $this->shop['id'];
            $submit_audit->appid = $open_platform_applet->appid;
            $submit_audit->primitive_name = $open_platform_applet->primitive_name;
            $submit_audit->template_id = request('template_id');
            $submit_audit->user_version = request('user_version');
            $submit_audit->applet_version = $this->handleAppletVersion($open_platform_applet);
            $submit_audit->status = 2;
            $submit_audit->create_time = time();
            $submit_audit->item_list = serialize($item_list);
            $submit_audit->category = serialize($this->handleCategory($category));
            $submit_audit->is_release = 0;
            $submit_audit->applet_commit_id = $app_commit->id;
            $submit_audit->save();
            $release = new AppletRelease();
            $release->sid = $submit_audit->id;
            $release->shop_id = $this->shop['id'];
            $release->appid = $open_platform_applet->appid;
            $release->template_id = request('template_id');
            $release->user_version = request('user_version');
            $release->applet_version = $submit_audit->applet_version;
            $release->create_time = time();
            $release->category = $submit_audit->category;
            $release->save();
        }
        return $this->output(['success' => 1]);
    }

    private function handleItem($v, $k)
    {
        switch ($k) {
            case 0:
                $v['address'] = 'pages/index/index';
                $v['tag'] = $v['first_class'];
                $v['title'] = '首页';
                return $v;
                break;
//            case 1:
//                $v['address'] = 'pages/subscibe/subscibe';
//                $v['tag'] = $v['first_class'];
//                $v['title'] = '订阅';
//                return $v;
//                break;
            case 1:
                $v['address'] = 'pages/personal/personal';
                $v['tag'] = $v['first_class'];
                $v['title'] = '个人';
                return $v;
                break;
        }
    }

    private function handleAppletVersion($open_platform_applet)
    {
        $applet_version = applet_tag_increment($open_platform_applet->applet_version);
        $open_platform_applet->applet_version = $applet_version;
        $open_platform_applet->save();
        return $applet_version;
    }

    //查询某个指定版本的审核状态

    public function getAuditstatus()
    {
        $url = config('define.open_platform.wx_applet.api.get_auditstatus')
            . '?access_token=' . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        $params = ['auditid' => request('auditid')];
        $res = $this->curl_trait('POST', $url, $params);
        $submit_audit = AppletSubmitAudit::where($params)->first();
        if (!$submit_audit) {
            $this->error('no_applet_submitaudit');
        }
        $ret = [
            'success' => 0,
            'status'  => $submit_audit->status,
        ];
        $status = intval($res['status']);
        if ($status !== intval($submit_audit->status)) {
            if ($status == 1) {
                $submit_audit->status = $status;
                $submit_audit->reason = $res['reason'];
                $submit_audit->audit_time = time();
                $params = [
                    'title'   => '审核失败',
                    'content' => $res['reason']
                ];
            }
            if ($status == 0) {
                $submit_audit->status = $status;
                $submit_audit->audit_time = time();
                $params = [
                    'title'   => '审核成功',
                    'content' => '您的小程序已经审核成功,可以发布上线了。'
                ];
            }
            $submit_audit->callback = $res ? serialize($res) : [];
            $submit_audit->save();
            $ret = [
                'success'        => 1,
                'status'         => $status,
                'button_display' => ($status === 0) ? 0 : 1
            ];
            $params['shop_id'] = $submit_audit->shop_id;
            $this->systemEvent($params);
        }
        return $this->output($ret);
    }

    private function systemEvent($params)
    {
        event(new SystemEvent($params['shop_id'], $params['title'], $params['content'], 0,
            -1, '系统管理员'));
    }

    //查询最新一次提交的审核状态
    public function getLatestAuditstatus()
    {
        $url = config('define.open_platform.wx_applet.api.get_latest_auditstatus')
            . '?access_token=' . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        $res = $this->curl_trait('GET', $url);
        return $this->output($res);
    }

    //发布已通过审核的小程序
    public function release()
    {
        $this->validateWith(['id' => 'required']);
        $url = config('define.open_platform.wx_applet.api.release')
            . '?access_token=' . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        $res = $this->curl_trait('POST', $url, new \stdClass());
        if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
            $where = ['sid' => request('id'), 'shop_id' => $this->shop['id']];
            $release = AppletRelease::where($where)->first();
            if (!$release) {
                $this->error('no_data');
            }
            $release->release_time = time();
            $release->save();
            $submit_audit = AppletSubmitAudit::where('id', request('id'))->first();
            if (!$submit_audit) {
                $this->error('no_data');
            }
            $submit_audit->is_release = 1;
            $submit_audit->save();
        }
        //发布时需要设置最低代码库版本
//        $this->checkWeappCodeVersion();
        return $this->output(['success' => 1]);
    }

    //修改小程序线上代码的可见状态
    public function changeVisitstatus()
    {
        $url = config('define.open_platform.wx_applet.api.change_visitstatus')
            . '?access_token=' . $this->getAuthorizerAccessToken()['authorizer_access_token'];
        $params = ['action' => request('action')];
        $res = $this->curl_trait('POST', $url, $params);
        if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
            $this->output(['success' => 1]);
        }
    }

    //设置小程序业务域名(秀赞接入短书用）
    public function webViewDomain()
    {
        $action = request('action') ?: 'add';
        $authorizationData = $this->getAuthorizerAccessToken();
        $authorizer_access_token = $authorizationData['authorizer_access_token'];
        if (IS_DOMAIN || $action === 'get') {
            $url = config('define.open_platform.wx_applet.api.web_view_domain').'?access_token=' . $authorizer_access_token;
            $params = ['action' => $action];
            if ($params['action'] == 'add') {
                $params['webviewdomain'] = [
                    'https://member.xiuzan.com',
                    'https://public.xiuzan.com',
                    'https://result.xiuzan.com',
                    'https://form.xiuzan.com',
                    'https://pimg.xiuzan.com',
                    'https://h5.xiuzan001.cn',
                    'https://h5.xiuzan.com',
                ];
            }
            try {
                $res = $this->curl_trait('POST', $url, $params);
            }catch (\Exception $exception){
                $params['action'] = 'set';
                $res = $this->curl_trait('POST', $url, $params);
            }
            if (isset($res['errmsg']) && $res['errmsg'] == 'ok') {
                if ($params['action'] != 'get') {
                    return $this->output(['success' => 1]);
                } else {
                    return $this->output($res);
                }
            }
        }
        return $this->output(['success' => 1]);
    }

    /**
     * 设置小程序代码库最低版本号
     */
    private function checkWeappCodeVersion(){
        try {
            $weapp_now_version = $this->getWeappNowVersion();
            if ($weapp_now_version < WEAPP_SUPPORT_LOWEST_VERSION) {
                $this->setWeappNowVersion(WEAPP_SUPPORT_LOWEST_VERSION);
            }
        }catch (\Exception $e) {

        }
    }

    /**
     * 设置小程序最低代码库版本号
     * @param $version
     */
    private function setWeappNowVersion($version){
        $url = config('define.open_platform.wx_applet.api.setweappsupportversion')
            . '?access_token=' . $this->getAuthorizerAccessToken($this->shop['id'])['authorizer_access_token'];
        $params = ['version' => $version];
        $res = $this->curl_trait('POST', $url, $params);
        return $res;
    }

    /**
     * 获取小程序代码库版本号
     * @return string
     */
    private function getWeappNowVersion(){
        $now_version = '';
        $url = config('define.open_platform.wx_applet.api.getweappsupportversion')
            . '?access_token=' . $this->getAuthorizerAccessToken($this->shop['id'])['authorizer_access_token'];
        $res = $this->curl_trait('POST', $url);
        if($res && isset($res['now_version'])){
            $now_version = $res['now_version'];
        }
        return $now_version;
    }
}

