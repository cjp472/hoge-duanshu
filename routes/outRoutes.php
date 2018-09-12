<?php
//内容管理
Route::group([
    'namespace' => 'Admin\Finance',
], function () {
    Route::post('/qcloud/settlement/', 'OrderController@settlement')->name('qcloudSettlement');
//    Route::post('/order/callback', 'OrderController@orderCallback')->middleware('check.pay.signature')->name('orderCallback');
//    Route::post('/order/notify', 'CenterController@orderCallback')->name('orderCenterCallback');
    Route::post('/order/notify', 'OrderController@orderCallback')->name('orderCallback');
    Route::post('/order/callback', 'OrderController@orderPayCallback')->middleware('check.pay.signature')->name('orderCenterCallback');

    Route::post('/store_webhooks/', 'OrderController@webhooks')->middleware('check.service.signature')->name('serviceWebhooks');
    Route::post('/admire/callback', 'AdmireController@admireCallback')->middleware('check.pay.signature')->name('admireCallback');
    Route::post('/applet/order/callback', 'AppletController@appletOrderCallback')->middleware('check.wechat.callback')->name('appletOrderCallback');
    Route::post('/applet/admire/callback', 'AdmireController@appletAdmireCallback')->middleware('check.wechat.callback')->name('appletAdmireOrderCallback');

    //拼团失败python通知php进行退款
    Route::post('/server/fight/callback', 'PayController@fightGroupCallback')->middleware('check.service.signature')->name('fightGroupCallback');
    //拼团成功回调
    Route::post('/server/fight/complete/callback', 'PayController@fightGroupCompleteCallback')->middleware('check.service.signature')->name('fightGroupCompleteCallback');
    //拼团失败python通知php进行退款
    Route::post('/applet/refund/callback', 'AppletController@appletRefundCallback')->middleware('check.wechat.callback')->name('appletRefundCallback');
    //退款失败重试
    Route::post('/server/fight/refund/retry', 'PayController@fightGroupRefundRetry')->middleware('check.service.signature')->name('fightGroupRefundRetry');
    //拼团失败重试
    Route::post('/server/fight/retry', 'PayController@fightGroupRetry')->middleware('check.service.signature')->name('fightGroupRetry');

    //拼团失败老数据处理
    Route::get('/server/fight/order','PayController@fightGroupOldFailedOrder')->name('fightGroupOldFailedOrder');
});

Route::group([
    'namespace' => 'Admin\Material',
], function () {
    Route::post('/admin/video/transcode/callback', 'VideoController@callback')->name('transcodeCallback');
    Route::get('/sync/video/class', 'VideoController@syncVideoClass')->middleware('log')->name('syncVideoClass');
});

Route::group([
    'namespace' => 'H5\Material',
], function () {
    Route::post('material/uploads', 'MaterialController@uploads')->name('uploadsMaterial');
});




Route::group([
    'namespace' => 'Admin\Setting',
    'middleware'    => 'check.service.signature',
], function () {
    //认证回调
    Route::post('/verify/callback', 'WebsiteController@verifyCallback')->name('verifyCallback');
    //提现账户回调
    Route::post('/withdraw/callback/account/', 'WebsiteController@withdrawAccountCallback')->name('withdrawAccountCallback');
    //提现回调通知
    Route::post('/withdraw/callback/workorder/', 'WebsiteController@withdrawNotify')->name('withdrawNotify');
    Route::get('/user/info/', 'WebsiteController@getUserInfo')->name('getUserInfo');
    //会员提现回调
    Route::post('/m-withdraw/callback/workorder/', 'WebsiteController@memberWithdrawCallback')->name('memberWithdrawCallback');
    Route::get('/withdraw/fee-rate/', 'WebsiteController@withdrawFeeRate')->name('withdrawFeeRate');

});

Route::group([
    'namespace' => 'Manage\Logs',
    'prefix'   => 'log',
], function () {
    Route::get('/create/error', 'ErrorLogsController@createErrorLog')->name('createErrorLog');
});

Route::group([
    'namespace' => 'Admin\Cache',
    'prefix' => 'cache',
],function (){
    Route::get('/problemStatus', 'SyncController@problemStatus')->name('problemStatus');
    Route::get('/livePattern', 'SyncController@livePattern')->name('livePattern');
    Route::get('/praiseStatus', 'SyncController@praiseStatus')->name('praiseStatus');
    Route::get('/praise/sum', 'SyncController@praiseSum')->name('praiseSum');
    Route::get('/play/count', 'SyncController@playCount')->name('playCountSync');
    Route::get('/clear/shop', 'SyncController@clearShopCache')->name('clearShopCache');
    Route::get('/play/count', 'SyncController@playCount')->name('playCount');
    Route::get('/payment/sync', 'SyncController@paymentSync')->name('paymentSync');
    Route::get('/problem/status', 'SyncController@problemStatus')->name('problemStatus');
    Route::get('/live/pattern', 'SyncController@livePattern')->name('livePattern');
    Route::get('/praise/status', 'SyncController@praiseStatus')->name('praiseStatus');
    Route::get('/wechat/member', 'SyncController@wechatMember')->name('wechatMember');
    Route::get('/subscribe/sync', 'SyncController@subscribeSync')->name('subscribeSync');
    Route::get('/live/message', 'SyncController@liveMessage')->name('liveMessage');
});

//微信服务器时间推送
Route::group([
    'namespace' => 'Manage\Shop',
], function () {
    Route::post('platform/event', 'AppletController@openPlatformEvent')->name('openPlatformEvent');
    Route::post('manage/applet/upload','AppletController@appletUpload')->name('getAppletUpload');

});
//生成二维码
Route::group([
    'namespace' => 'Admin\Material',
], function () {
    Route::get('/qrcode/make', 'MaterialController@qrcodeMake')->middleware('check.sms.signature')->name('qrcodeMake');
    Route::post('/upload/url', 'MaterialController@uploadUrl')->name('uploadUrl');
    Route::post('/qiniu/callback', 'MaterialController@qiniuCallback')->name('uploadUrl');
});

//同步专栏到内容表测试
Route::group([
    'namespace' => 'Admin',
], function () {
    Route::get('/sync/column', 'BaseController@syncColumn')->name('syncColumn');
});

Route::group([
    'namespace' => 'H5\Flow',
], function () {
    Route::post('/flow/signature', 'FlowController@flowSignature')->name('flowSignature');
});

//推广员
Route::group([
    'namespace' => 'Admin\Promotion'
], function () {
    //推广员佣金计算参数获取
    Route::post('/open/commission_rate/config/','IndexController@rateConfig')->middleware('check.service.signature')->name('rateConfig');
});

//获取证书
Route::group([
    'namespace'  => 'Admin\Setting',
    'prefix'     => 'certificate'
],function () {
    //获取证书
    Route::get('/{shop_id}/{file_name}', 'WebsiteController@getCertificate')->middleware('log')->name('getCertificate');

});

//移动端上传文件签名
Route::group([
    'namespace' => 'H5\Material',
], function () {
    //h5上传文件签名
    Route::get('h5/cos/signature', 'MaterialController@signature')->middleware('h5.log')->name('h5SignatureCos');
});


Route::group([
    'namespace' => 'Admin\Notice',
], function () {
    Route::get('/send/not/verify/notice', 'SystemNoticeController@sendUnVerify')->name('sendUnVerify');
    Route::get('/statistics/shop/mobile', 'SystemNoticeController@statisticsShopMobile')->name('statisticsShopMobile');
});

//SDK校验
Route::group([
    'namespace' => 'H5\Shop'
], function () {
    Route::post('/sdk/valid','SdkController@check')->middleware('sdk.valid')->name('sdkValid');
});


Route::group([
    'namespace' => 'Admin\Setting'
], function () {
    //obs直播回调
    Route::post('/obs/callback/','ObsController@obsCallback')->name('obsCallback');
    Route::post('/module/live/open','ObsController@openLive')->middleware('check.python.signature')->name('obsLiveOpen');
    //获取推流地址列表
    Route::get('/obs/push/lists','ObsController@getPushUrlList')->middleware('check.service.signature')->name('getPushUrlList');
});
