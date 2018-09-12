<?php

Route::get('/postage', 'Admin\Setting\WebsiteController@postage')->name('postage');

Route::group([
    'namespace'  => 'Admin',
    'middleware' => 'web',
    'prefix'     => 'admin',
], function () {
    //用户登录注册
    Route::auth();
    Route::post('/register', 'Auth\EmailRegisterController@register')->name('EmailRegister');
    Route::post('/register/email', 'Auth\EmailRegisterController@register')->name('EmailRegister');
    Route::post('/register/mobile', 'Auth\MobileRegisterController@register')->name('MobileRegister');
    Route::get('/email/active/{uid}/{code}', 'Auth\ActiveEmailController@active')->name('activeEmail');
    Route::get('/wechat/login', 'Auth\WechatController@login')->name('wechatLogin');
    Route::post('/partner/apply', 'Partner\PartnerController@applyPartner')->name('applyPartner');
    Route::get('/mobile/check', 'Auth\AccountController@checkMobile')->name('checkMobile');
    Route::get('/user/try', 'User\UserController@tryUser')->middleware('log:mobile')->name('tryUserCreate');
    //获取首页弹窗状态
    Route::get('/home/window', 'Setting\WebsiteController@getHomeWindowStatus')->name('getHomeWindowStatus');
    //修改首页弹窗展示状态
    Route::get('/home/window/set', 'Setting\WebsiteController@changeHomeWindowStatus')->name('changeHomeWindowStatus');
    //动态码登录
    Route::post('/dynamic/login', 'Auth\DynamicCodeController@login')->name('dynamicLogin');
    Route::post('/dynamic/register', 'Auth\DynamicCodeController@register')->name('dynamicRegister');
});

//会员管理
Route::group([
    'namespace' => 'H5\Client',
    'middleware'    => 'api.check',
    'prefix'    => 'h5/client',
], function () {
    //会员管理
    Route::get('/wechat', 'WechatController@callback')->middleware('h5.log')->name('h5WechatCallback');
    //微信jssdk签名
    Route::get('/sign', 'WechatController@sign')->name('WechatJsSdkSign');
    //会员管理
    Route::get('/applet/token',
        'WechatAppletController@appletSessionKey')->middleware('h5.log')->name('WXAppletSessionKey');
    //小程序登录
    Route::post('/applet', 'WechatAppletController@wxAppletLogin')->middleware([
        'h5.log',
        'applet'
    ])->name('WXAppletLogin');
    Route::get('/wxApplet/token',
        'WXAppletLoginController@appletSessionKey')->middleware('h5.log')->name('WXAppletSessionKey');
    Route::post('/wxApplet', 'WXAppletLoginController@WXAppletLogin')->middleware([
        'h5.log',
        'applet'
    ])->name('wxAppletLogin');
    Route::post('/member/login', 'LoginController@memberLogin')->middleware(['h5.log'])->name('memberLogin');

    //私密会员登录接口
    Route::post('/check/login', 'LoginController@userLogin')->middleware(['h5.log'])->name('userLogin');
    //私密会员登录接口
    Route::post('/logout', 'LoginController@userLogout')->middleware(['h5.log','member.check'])->name('userLogout');

    //私密会员登录接口
    Route::get('/check/private', 'LoginController@checkPrivateSettings')->name('checkPrivateSettings');

    Route::post('/wechat/phone', 'WXAppletLoginController@getPhoneNumber')->middleware(['h5.log'])->name('getPhoneNumber');
    Route::post('/applet/mobile/authorize', 'WXAppletLoginController@appletMobileAuthorize')->middleware(['h5.log'])->name('appletMobileAuthorize');
    Route::post('/check/mobile/bind', 'WXAppletLoginController@checkMobileBind')->middleware('shop.h5.check')->name('checkMobileBind');
    //检测手机号是否绑定h5会员
    Route::get('/check/mobile/bind', 'WXAppletLoginController@checkMobileBindH5')->middleware('shop.h5.check')->name('checkMobileBindH5');



});

Route::group([
    'namespace' => 'Admin\OpenPlatform',
    'middleware' => ['log'],
    'prefix'    => 'admin/official',
], function () {
    Route::any('/auth_event', 'OpenPlatformController@authEvent')->name('OpenPlatformAuthEvent');
    Route::get('/pre_auth_url', 'OpenPlatformController@preAuthUrl')->name('OpenPlatformPreAuthUrl');
    Route::any('/wx_callback', 'OpenPlatformController@wxCallback')->middleware('web', 'auth')->name('OpenPlatformWXCallback');
    Route::get('/unbind', 'OpenPlatformController@unbind')->middleware('web', 'auth')->name('OpenPlatformUnbind');
        Route::any('/{appid}/callback', 'PublicController@callback')->name('PublicCallback');
//    Route::any('/{appid}/callback', 'FullWebController@fullWebPublishUtil')->name('FullWebPublishUtil');
    Route::get('/redis', 'OpenPlatformController@getRedisData')->middleware('web', 'auth')->name('WXAppletGetRedisData');
    
    Route::get('/authorize_url', 'PublicController@authorizeUrl')->name('PublicAuthorizeUrl');
    Route::get('/get_access_token', 'PublicController@getAccessToken')->name('PublicGetAccessToken');
    Route::get('/get_userInfo', 'PublicController@getUserInfo')->name('PublicGetUserInfo');
});

Route::group([
    'namespace'  => 'Admin\OpenPlatform',
    'middleware' => ['web', 'auth', 'log'],
    'prefix'     => 'admin/wxApplet'
], function () {
    Route::post('/modify/domain', 'WXAppletController@modifyDomain')->name('WXAppletModifyDomain');
    Route::post('/bind/tester', 'WXAppletController@bindTester')->name('WXAppletBindTester');
    Route::post('/unbind/tester', 'WXAppletController@unBindTester')->name('WXAppletUnBindTester');
    Route::post('/commit', 'WXAppletController@commit')->name('WXAppletCommit');
    Route::get('/temporary/qrcode', 'WXAppletController@getTemporaryQrcode')->name('WXAppletGetTemporaryQrcode');
    Route::post('/submit/audit', 'WXAppletController@submitAudit')->name('WXAppletSubmitAudit');
    Route::post('/auditstatus', 'WXAppletController@getAuditstatus')->name('WXAppletGetAuditstatus');
    Route::get('/latest/auditstatus', 'WXAppletController@getLatestAuditstatus')->name('WXAppletGetLatestAuditstatus');
    Route::post('/release', 'WXAppletController@release')->name('WXAppletRelease');
    Route::post('/change/visitstatus', 'WXAppletController@changeVisitstatus')->name('WXAppletChangeVisitstatus');
    Route::get('/check/bind', 'WXAppletController@checkBind')->name('WXAppletCheckBind');
    Route::get('/check/submitaudit', 'WXAppletController@checkSubmitAudit')->name('WXAppletCheckSubmitAudit');
    Route::get('/check/release', 'WXAppletController@checkRelease')->name('WXAppletCheckRelease');
    Route::get('/check/commit', 'WXAppletController@checkCommit')->name('WXAppletCheckCommit');
    Route::get('/round/qrcode', 'AppletQrcodeController@getRoundQrcode')->name('WXAppletRoundQrcode'); //圆形有限制
    Route::get('/square/qrcode', 'AppletQrcodeController@getSquareQrcode')->name('WXAppletSquareQrcode'); //方形有限制
    Route::get('/unlimit/qrcode', 'AppletQrcodeController@getUnlimitQrcode')->name('WXAppletUnlimitQrcode'); //圆形无限制
    Route::get('/contentpreview/qrcode', 'AppletQrcodeController@getContentAppletPreview')->name('WXAppletContentPreview'); // 内容预览小程序码
    Route::post('/unlimit/qrcode/refresh', 'AppletQrcodeController@refreshUnlimitQrcode')->name('WXAppletRefreshUnlimitQrcode'); //刷新圆形无限制
    Route::post('/web/view/domain', 'WXAppletController@webViewDomain')->name('WXAppletWebViewDomain');

    Route::group([
        'namespace' => 'Servers',
        'prefix' => 'servers'
    ], function () {
        // 获取店铺下的公众号信息
        Route::get('public/list', 'ArticleController@publicList')->name('WXAppletServersPublicList');
        // 选择的图文列表
        Route::get('news/list', 'ArticleController@newsList')->name('wxAppletServersNewsList');
        // 将公众号图文推到短书
        Route::post('news/put2ds', 'ArticleController@put2Ds')->name('WXAppletServersNewsPut2Ds');
    });
});

//证书上传
Route::group([
    'namespace'  => 'Admin\Setting',
    'middleware' => ['web','auth','shop','permission.check','log'],
    'prefix'     => 'server/admin/certificate/'
],function () {
    //小程序退款上传API证书
    Route::post('/upload', 'WebsiteController@uploadCertificate')->name('uploadCertificate');
    //关闭小程序退款
    Route::get('/close', 'WebsiteController@closeAppletRefund')->name('closeAppletRefund');
});