<?php
//内容管理
Route::get('/content/change', '\App\Http\Controllers\H5\Material\MaterialController@change');
Route::group([
    'namespace' => 'Content',
    'prefix'    => 'content',
], function () {
    //内容下架公用接口
    //Route::get('/shelf', 'ContentController@shelf')->middleware('log:id')->name('contentShelf');
    Route::post('/shelf', 'ContentController@shelf')->middleware('log:id')->name('contentShelf');
    //内容设为试看
    Route::post('/test', 'ContentController@contentToTest')->middleware('log:content_id')->name('contentToTest');
    Route::get('/type','ContentController@getContentByType')->name('getContentByType');
    //内容添加到专栏，支持多个内容
    Route::post('/put/column','ContentController@contentToColumn')->middleware('log')->name('contentToColumn');
    Route::get('/top','ContentController@contentTop')->middleware('log')->name('contentTop');
    //内容排序
    Route::get('/sort','ContentController@sort')->middleware('log')->name('contentSort');
    //新建专栏专属内容
    Route::post('/column/exclusive','ContentController@createColumnContent')->name('createColumnContent');
    //更新专栏专属内容
    Route::put('/column/exclusive/{id}','ContentController@updateColumnContent')->name('updateColumnContent');
    //专栏下内容批量删除
    Route::delete('/column/batch/delete','ContentController@deleteColumnContent')->name('deleteColumnContent');
    //专栏下内容批量上下架
    Route::put('/column/batch/shelf','ContentController@shelfColumnContent')->name('shelfColumnContent');

    //图文接口
    Route::group([
        'prefix'    => 'article',
    ],function (){
        Route::get('/lists', 'ArticleController@lists')->name('articleList');
        Route::get('/detail/{id}', 'ArticleController@detail')->name('articleDetail');
        Route::post('/create', 'ArticleController@create')->middleware('log')->name('articleCreate');
        Route::post('/update', 'ArticleController@update')->middleware('log')->name('articleUpdate');
        //Route::delete('/delete/{id}', 'ArticleController@delete')->middleware('log:id')->name('articleDelete');
        Route::delete('/delete', 'ArticleController@delete')->middleware('log:id')->name('articleDelete');
    });
    //专栏接口
    Route::group([
        'prefix'    => 'column',
    ],function (){
        Route::get('/lists', 'ColumnController@lists')->name('columnList');
        Route::get('/contents', 'ColumnController@contents')->name('columnContents');
        Route::get('/detail/{id}', 'ColumnController@detail')->name('columnDetail');
        Route::post('/create', 'ColumnController@create')->middleware('log')->name('columnCreate');
        Route::post('/update', 'ColumnController@update')->middleware('log')->name('columnUpdate');
        //Route::get('/shelf', 'ColumnController@shelf')->middleware('log:id')->name('columnShelf');
        Route::post('/shelf', 'ColumnController@shelf')->middleware('log:id')->name('columnShelf');
        //Route::get('/finish', 'ColumnController@finish')->middleware('log:id')->name('columnFinish');
        Route::post('/finish', 'ColumnController@finish')->middleware('log:id')->name('columnFinish');
        Route::get('/display', 'ColumnController@display')->middleware('log:id')->name('columnDisplay');
        Route::get('/top', 'ColumnController@top')->middleware('log')->name('columnTop');
        Route::get('/change/payment', 'ColumnController@changePayment')->middleware('log:id')->name('changePayment');
        //专栏排序
        Route::get('/sort', 'ColumnController@sort')->middleware('log:id')->name('columnSort');
        //专栏内容排序
        Route::get('/sort/content', 'ColumnController@contentSort')->middleware('log:id')->name('columnContentSort');
    });
    //音频接口
    Route::group([
        'prefix'    => 'audio',
    ],function (){
        Route::get('/lists', 'AudioController@lists')->name('audioList');
        Route::get('/detail/{id}', 'AudioController@detail')->name('audioDetail');
        Route::post('/create', 'AudioController@create')->middleware('log')->name('audioCreate');
        Route::post('/update', 'AudioController@update')->middleware('log')->name('audioUpdate');
        //Route::delete('/delete/{id}', 'AudioController@delete')->middleware('log:id')->name('audioDelete');
        Route::delete('/delete', 'AudioController@delete')->middleware('log:id')->name('audioDelete');
    });
    //视频接口
    Route::group([
        'prefix'    => 'video',
    ],function (){
        Route::get('/lists', 'VideoController@lists')->name('videoList');
        Route::get('/detail/{id}', 'VideoController@detail')->name('videoDetail');
        Route::post('/create', 'VideoController@create')->middleware('log')->name('videoCreate');
        Route::post('/update', 'VideoController@update')->middleware('log')->name('videoUpdate');
        //Route::delete('/delete/{id}', 'VideoController@delete')->middleware('log:id')->name('videoDelete');
        Route::delete('/delete', 'VideoController@delete')->middleware('log:id')->name('videoDelete');
    });
    //直播接口
    Route::group([
        'prefix'    => 'alive',
    ],function (){
        Route::get('/lists', 'AliveController@lists')->name('aliveList');
        Route::get('/detail/{id}', 'AliveController@detail')->name('aliveDetail');
        Route::post('/create', 'AliveController@create')->middleware('log')->name('aliveCreate');
        Route::post('/update', 'AliveController@update')->middleware('log')->name('aliveUpdate');
        //Route::delete('/delete/{id}', 'AliveController@delete')->middleware('log:id')->name('aliveDelete');
        Route::delete('/delete', 'AliveController@delete')->middleware('log:id')->name('aliveDelete');
    });

    Route::group([
        'prefix'    => 'course'
    ],function () {
        //课时
        Route::post('/class/create','ClassController@createClass')->middleware('log')->name('createClass');
        Route::post('/class/update','ClassController@updateClass')->middleware('log')->name('updateClass');
        Route::delete('/class/delete','ClassController@deleteClass')->middleware('log')->name('deleteClass');
        Route::get('/class/list','ClassController@listClass')->name('listClass');
        Route::get('/class/detail','ClassController@detailClass')->name('detailClass');
        Route::get('/class/top','ClassController@topClass')->middleware('log')->name('topClass');
        Route::get('/class/sort','ClassController@sortClass')->middleware('log')->name('sortClass');
        //章节
        Route::post('/chapter/create','ChapterController@createChapter')->middleware('log')->name('createChapter');
        Route::post('/chapter/update','ChapterController@updateChapter')->middleware('log')->name('updateChapter');
        Route::delete('/chapter/delete','ChapterController@deleteChapter')->middleware('log')->name('deleteChapter');
        Route::get('/chapter/list','ChapterController@listChapter')->name('listChapter');
        Route::get('/chapter/detail','ChapterController@detailChapter')->name('detailChapter');
        Route::get('/chapter/top','ChapterController@topChapter')->middleware('log')->name('topChapter');
        Route::get('/chapter/sort','ChapterController@sortChapter')->middleware('log')->name('sortChapter');
        //课程
        Route::get('/lists', 'CourseController@lists')->name('courseList');
        Route::get('/detail/{id}', 'CourseController@detail')->name('courseDetail');
        Route::post('/create', 'CourseController@createCourse')->middleware('log')->name('courseCreate');
        Route::post('/update', 'CourseController@updateCourse')->middleware('log')->name('courseUpdate');
        //Route::get('/shelf', 'CourseController@shelf')->middleware('log')->name('courseShelf');
        Route::post('/shelf', 'CourseController@shelf')->middleware('log')->name('courseShelf');
        //Route::get('/finish', 'CourseController@finish')->middleware('log')->name('courseFinish');
        Route::post('/finish', 'CourseController@finish')->middleware('log')->name('courseFinish');
        Route::get('/top', 'CourseController@top')->middleware('log')->name('courseTop');
        //课程
        Route::get('/sort', 'CourseController@sort')->middleware('log:id')->name('courseSort');
        //课时添加内容列表
        Route::get('/class/content', 'ClassController@classContentList')->name('classContentList');
        //添加内容到课时
        Route::post('/class/content', 'ClassController@putContentToClass')->middleware('log')->name('putContentToClass');
    });

});

//评论管理
Route::group([
    'namespace' => 'Comment',
    'prefix'    => 'comment',
], function () {
    //评论列表 或者 关于内容的评论列表
    Route::get('/lists', 'CommentController@lists')->name('commentList');
    //关于用户的评论列表
    Route::get('/user', 'CommentController@userComments')->name('userCommentLists');
    //系统回复用户的回复列表
    Route::get('/reply/lists', 'CommentController@replyList')->name('systemReplyList');
    //管理员进行回复
    Route::post('/reply', 'CommentController@adminReply')->middleware('log:recipients_name')->name('adminReply');
    //显示隐藏状态切换
    //Route::get('/status', 'CommentController@changeType')->middleware('log:id')->name('changeStatusComment');
    Route::post('/status', 'CommentController@changeType')->middleware('log:id')->name('changeStatusComment');
    //精选状态切换
    Route::get('/choice', 'CommentController@changeChoice')->middleware('log:id')->name('changeTypeComment');
});

//消息管理
Route::group([
    'namespace' => 'Notice',
    'prefix'    => 'notice',
], function () {
    //消息列表
    Route::get('/lists', 'NoticeController@lists')->name('noticeList');
    //单条消息详情
    Route::get('/{id}', 'NoticeController@detail')->name('noticeDetail')->where(['id'=>'[0-9]+']);
    //发送单条消息
    Route::post('/send/one', 'NoticeController@sendToOne')->middleware('log:recipients_name')->name('noticeSendToOne');
    //群发消息
    Route::post('/send/all', 'NoticeController@sendToAll')->middleware('log:content')->name('noticeSendToAll');
    //群发消息编辑
    Route::patch('/send/all/{id}', 'NoticeController@updateSendToAll')->middleware('log:content')->name('noticeUpdateSendToAll')->where(['id'=>'[0-9]+']);
    //撤回
    Route::get('/revoke', 'NoticeController@revoke')->middleware('log:id')->name('noticeRevoke');
    //查看某个用户的消息
    Route::get('/user/list', 'NoticeController@userList')->name('noticeUserList');
    //消息模板新增
    Route::post('/template', 'TemplateController@templateCreate')->middleware('log')->name('noticeTemplateCreate');
    //消息模板更新
    Route::patch('/template/{id}', 'TemplateController@templateUpdate')->middleware('log')->name('noticeTemplateUpdate');
    //消息模板列表
    Route::get('/template', 'TemplateController@templateList')->name('noticeTemplateList');

    //系统消息
    Route::group([
        'prefix'    => 'system',
    ],function (){
        Route::get('/lists', 'SystemNoticeController@lists')->name('systemNoticeList');
        Route::get('/detail', 'SystemNoticeController@detail')->middleware('log')->name('systemNoticeDetail');
        Route::get('/banner/detail', 'SystemNoticeController@bannerDetail')->name('systemNoticeBannerDetail');
        Route::get('/update', 'SystemNoticeController@update')->middleware('log')->name('systemNoticeUpdate');
        Route::get('/not/read', 'SystemNoticeController@noReadLists')->name('systemNoticeNotRead');
        Route::get('/number', 'SystemNoticeController@noticeNum')->name('systemNoticeNumber');
    });
    //小程序消息
    Route::group([
        'prefix'    => 'applet',
    ],function (){
        Route::get('/detail', 'AppletNoticeController@detail')->name('appletNoticeDetail');
        Route::get('/update', 'AppletNoticeController@update')->middleware('log')->name('appletNoticeUpdate');
        Route::get('/status', 'AppletNoticeController@status')->name('appletNoticeStatus');
        Route::get('/version', 'AppletNoticeController@version')->name('appletNoticeVersion');
    });

});

//轮转图
Route::group([
    'namespace' => 'Banner',
    'prefix'    => 'banner',
], function () {
    Route::get('/lists', 'BannerController@lists')->name('bannerList');
    Route::get('/detail/{id}', 'BannerController@detail')->name('bannerDetail');
    Route::post('/create', 'BannerController@create')->middleware('log')->name('bannerCreate');
    Route::post('/update', 'BannerController@update')->middleware('log')->name('bannerUpdate');
    Route::get('/delete/{id}', 'BannerController@delete')->middleware('log:id')->name('bannerDelete');
    Route::get('/shelf', 'BannerController@shelf')->middleware('log:id')->name('bannerShelf');
    Route::get('/top', 'BannerController@top')->middleware('log')->name('bannerTop');
    //轮播图排序
    Route::get('/sort', 'BannerController@sort')->middleware('log')->name('bannerSort');
});

//会员管理
Route::group([
    'namespace' => 'User',
    'prefix'    => 'user',
], function () {
    Route::get('/lists', 'UserController@lists')->name('userList');
    Route::get('/{id}', 'UserController@detail')->name('userDetail')->where(['id'=>'[a-z0-9]{32}']);
    Route::patch('/{id}', 'UserController@update')->name('userUpdate')->middleware('log:nick_name')->where(['id'=>'[a-z0-9]{32}']);
    Route::get('/download', 'UserController@downloadUser')->middleware('log')->name('downloadUser');

});
//财务管理
Route::group([
    'namespace' => 'Finance',
], function () {
    //订单管理
    Route::group([
        'prefix'    => 'order',
    ],function (){
        Route::get('/lists', 'OrderController@lists')->name('orderList');
        Route::get('/download', 'OrderController@download')->middleware('log:time')->name('orderDownload');
        Route::get('/user/{user_id}', 'OrderController@getMemberOrder')->name('userOrder');
    });

    //开通记录
    Route::group([
        'prefix'    => 'pay',
    ],function (){
        Route::get('/lists', 'PayController@lists')->name('payList');
        Route::get('/download', 'PayController@download')->middleware('log')->name('payDownload');
        Route::delete('/{uid}/{type}/{cid}/{ptype}', 'PayController@deletePay')->middleware('log')->name('payDelete');
    });

    //提现记录
    Route::group([
        'prefix'    => 'withdraw',
    ],function (){
        Route::get('/lists', 'WithdrawController@lists')->name('withdrawList');
        Route::get('/account', 'WithdrawController@account')->name('totalAccount');
        Route::post('/bind/wechat', 'WithdrawController@bingWechat')->name('bingWechat');
    });

    //订单中心
    Route::group([
        'prefix'    => 'center',
    ],function (){
        //订单中心列表
        Route::get('/lists', 'CenterController@lists')->name('centerList');
        //订单详情
        Route::get('/lists/{id}', 'CenterController@detail')->name('centerDetail');
        //修改订单状态
        Route::get('/status', 'CenterController@status')->middleware('log:order_num')->name('centerStatus');
        //冻结订单
        Route::get('/blocked', 'CenterController@blocked')->name('centerBlocked');
        //总收入
        Route::get('/total', 'CenterController@incomeTotal')->name('centerTotal');

        Route::get('/assets', 'CenterController@getAssets')->name('getAssets');

    });

    //商品
    Route::group([
        'prefix'    => 'goods',
    ],function (){
        Route::get('/lists', 'GoodsController@getGoodsList')->name('goodsList');
    });

});

//店铺设置
Route::group([
    'namespace' => 'Setting',
    'prefix'    => 'shop'
], function () {
    Route::get('/index', 'WebsiteController@index')->name('shopIndex');
    Route::post('/update', 'WebsiteController@update')->middleware('log')->name('updateShop');
    Route::get('/index/client', 'WebsiteController@clientIndex')->middleware('log')->name('indexShopClient');
    Route::get('/save/client', 'WebsiteController@saveClient')->middleware('log')->name('saveShopClient');
    Route::get('/update/client', 'WebsiteController@updateClient')->middleware('log')->name('updateShopClient');
    Route::get('/version', 'WebsiteController@version')->name('shopVersion');
    Route::get('/verify/detail', 'WebsiteController@verifyDetail')->name('verifyDetail');
    Route::get('/verify/status', 'WebsiteController@verifyStatus')->middleware('log')->name('verifyStatus');
    Route::get('uv', 'WebsiteController@setUserViews')->name('setUserViews');
    Route::get('pv', 'WebsiteController@setPersonViews')->name('setPersonViews');
    Route::get('click', 'WebsiteController@setClickQuantity')->name('setClickQuantity');
    //查看分享信息
    Route::get('/share', 'ShareController@detail')->name('shareDetail');
    //编辑分享信息
    Route::patch('/share', 'ShareController@update')->middleware('log')->name('shareUpdate');
    //设置公告显示状态
    Route::get('/announce', 'WebsiteController@announceStatus')->middleware('log')->name('announceStatus');
    //记录用户点击高级版升级、全平台申请按钮数据
    Route::get('/button/clicks', 'WebsiteController@setButtonClicks')->middleware('log')->name('setButtonClicks');
    //店铺小程序极速登陆开关设置
    Route::get('/applet/fast', 'WebsiteController@appletFastLogin')->middleware('log')->name('appletFastLogin');

    //首页分类导航接口
    Route::group([
        'prefix' => 'navigation',
    ], function () {
//        //列表接口
//        Route::get('/lists', 'TypeController@lists')->name('navigationList');
//        //详情接口接口
//        Route::get('/detail', 'TypeController@detail')->name('navigationList');
//        //新增分类
//        Route::get('/add', 'TypeController@addType')->middleware('log')->name('navigationAdd');
//        //删除分类
//        Route::get('/delete', 'TypeController@deleteType')->middleware('log:id')->name('navigationDelete');
//        //更新接口
//        Route::get('/update', 'TypeController@updateType')->middleware('log')->name('navigationUpdate');
//        //修改显示隐藏
//        Route::get('/status', 'TypeController@changeStatus')->middleware('log:id')->name('navigationStatus');
//        //排序
//        Route::get('/sort', 'TypeController@sortType')->middleware('log:id')->name('navigationSort');

        //内容选择
        Route::get('/contents', 'NavigationController@contents')->name('navigationContents');

        //导航新增
        Route::post('/create', 'NavigationController@create')->name('navigationCreate');
        //导航列表
        Route::get('/lists', 'NavigationController@lists')->name('navigationList');
        //导航修改
        Route::put('/update', 'NavigationController@update')->name('navigationUpdate');
        //导航删除
        Route::delete('/delete', 'NavigationController@delete')->name('navigationDelete');
        //导航状态修改
        Route::get('/status', 'NavigationController@status')->name('navigationStatus');
        //导航排序
        Route::get('/sort', 'NavigationController@sort')->name('navigationSort');
        //导航详情
        Route::get('/detail', 'NavigationController@detail')->name('navigationDetail');
    });

    //内容分类
    Route::group([
        'prefix' => 'class',
    ], function () {
        //新增分类
        Route::post('/create', 'NavigationController@createClass')->name('createClass');
        //分类列表
        Route::get('/lists', 'NavigationController@classLists')->name('classLists');
        //分类删除
        Route::delete('/{id}', 'NavigationController@classDelete')->name('classDelete');
        //分类更新
        Route::put('/{id}', 'NavigationController@classUpdate')->name('classUpdate');
        //分类详情
        Route::get('/{id}', 'NavigationController@classDetail')->name('classDetail');
        //分类数据删除
        Route::delete('/content/delete', 'NavigationController@deleteClassContent')->name('deleteClassContent');
        //分类数据排序
        Route::get('/content/sort', 'NavigationController@sortClassContent')->name('sortClassContent');
    });


    //数据分析
    Route::group([
        'prefix' => 'analysis',
    ], function () {
        //店铺总数统计
        Route::get('/shop/total', 'DashboardController@shopTotal')->name('shopTotal');
        //用户总数统计
        Route::get('/user/total', 'DashboardController@userTotal')->name('userTotal');
        //用户增长分析
        Route::get('/user/growth', 'DashboardController@userGrowth')->name('userGrowth');
        //今日概况
        Route::get('/situations', 'DashboardController@todaySituation')->name('todaySituation');
        //内容分析
        Route::get('/content', 'DashboardController@analysisContent')->name('analysisContent');
        //内容分析-折线图
        Route::get('/content/chart', 'DashboardController@chartAnalysis')->name('analysisChartContent');
        //优质内容top10
        Route::get('/high/content', 'DashboardController@highContent')->name('highContent');
        //今日昨日新增收入
        Route::get('/income/total', 'DashboardController@incomeTotal')->name('incomeTotal');
        Route::get('/order/total', 'DashboardController@orderTotal')->name('orderTotal');
        //收入增长分析
        Route::get('/income/growth', 'DashboardController@incomeGrowth')->name('incomeGrowth');
        Route::get('/order/growth', 'DashboardController@orderGrowth')->name('orderGrowth');
        //收入占比
        Route::get('/income/percent', 'DashboardController@incomePercent')->name('incomePercent');
        Route::get('/order/percent', 'DashboardController@orderPercent')->name('orderPercent');
        //用户分布
        Route::get('/user/distribute', 'DashboardController@userDistribute')->name('userDistribute');
        //内容数据统计
        Route::get('/content/statistics', 'DashboardController@contentStatistics')->name('contentStatistics');
        //直播数据统计单独处理
        Route::get('/alive/statistics', 'DashboardController@aliveStatistics')->name('aliveStatistics');
    });

    Route::group([
        'prefix' => 'applet',
    ], function () {
        // 小程序二维码
        Route::get('/qrcode/qrcode', 'AppletQrcodeController@wxaQrcode')->name('qrcodeQrcode');
        // 小程序码
        Route::get('/qrcode/code', 'AppletQrcodeController@wxaCode')->name('qrcodeCode');
        // 无限制小程序码
        Route::get('/qrcode/codeunlimit', 'AppletQrcodeController@wxaCodeUnlimit')->name('qrcodeCodeUnlimit');
    });

    //配色
    Route::group([
        'prefix' => 'color',
    ], function () {
        Route::get('/lists', 'ColorController@lists')->name('colorLists');
        Route::get('/choose', 'ColorController@chooseH5Color')->middleware('log')->name('chooseH5Color');
        Route::get('/applet/choose', 'ColorController@chooseAppletColor')->middleware('log')->name('chooseAppletColor');
    });

    //短信
    Route::group([
        'prefix' => 'message',
    ], function () {
        Route::get('/statistics', 'MessageController@statistics')->name('messageStatistics');
        Route::get('/detail', 'MessageController@detail')->name('messageDetail');
    });

    //帮助中心列表
    Route::get('/help/list', 'HelpCenterController@getList')->name('helpList');

    //sdk
    Route::group([
        'prefix' => 'sdk',
    ], function () {
        Route::post('/apply', 'SdkController@apply')->name('sdkApply');
        Route::get('/display', 'SdkController@display')->name('sdkDisplay');
        Route::get('/reset', 'SdkController@reset')->name('sdkReset');
        Route::put('/edit', 'SdkController@edit')->name('sdkEdit');
        Route::delete('/delete', 'SdkController@delete')->name('sdkDelete');
        //短信
    });

    Route::group([
        'prefix' => 'protocol',
    ], function () {
        Route::get('/status', 'OrderProtocolController@protocolStatus')->name('protocolStatus');
        Route::get('/detail', 'OrderProtocolController@protocolDetail')->name('protocolDetail');
        Route::get('/export', 'OrderProtocolController@export')->name('protocolExport');
        Route::get('/lists', 'OrderProtocolController@lists')->name('protocolLists');
        //订购脚本
        Route::get('/job', 'OrderProtocolController@job')->name('protocolJob');
    });

    Route::group([
        'prefix' => 'info',
    ],function (){
        Route::post('/set', 'WebsiteController@setShopInfo')->name('setShopInfo');
        Route::get('/detail', 'WebsiteController@shopInfoDetail')->name('shopInfoDetail');
    });
    
});

//反馈管理
Route::group([
    'namespace' => 'Feedback',
    'prefix'    => 'feedback',
], function () {
    //反馈列表
    Route::get('/lists', 'FeedbackController@lists')->name('feedbackList');
    //某个用户的所有反馈列表
    Route::get('/lists/user', 'FeedbackController@userFeedback')->name('UserFeedbackList');
    Route::get('/update', 'FeedbackController@updateTime')->middleware('log:id')->name('updateTime');
});

//附件管理
Route::group([
    'namespace' => 'Material',
], function () {
    Route::get('/cos/signature', 'ImageController@signature')->middleware('shop.verify','log')->name('signatureCos');

    //点播上传
    Route::group([
        'prefix'    => 'video',
    ],function (){
        Route::get('/signature', 'VideoController@signature')->middleware('shop.verify','log')->name('videoSignture');
        Route::get('/signature/new', 'VideoController@newSignature')->middleware('shop.verify','log')->name('videoNewSignature');
    });

    //直播资源管理
    Route::group([
        'prefix'    => 'material',
    ],function (){
        //列表
        Route::get('/lists', 'MaterialController@lists')->name('materialList');
        //新增
        Route::post('/create', 'MaterialController@saveMaterial')->middleware('log')->name('materialCreate');
        //更新
        Route::post('/update', 'MaterialController@updateMaterial')->middleware('log')->name('materialUpdate');
        //详情
        Route::get('/detail', 'MaterialController@detail')->name('materialDetail');
        //删除
        //Route::get('/delete', 'MaterialController@deleteMaterial')->middleware('log:id')->name('materialDelete');
        Route::post('/delete', 'MaterialController@deleteMaterial')->middleware('log:id')->name('materialDelete');
        //置顶
        Route::get('/top', 'MaterialController@topMaterial')->middleware('log:id')->name('materialTop');
        //隐藏
        //Route::get('/status', 'MaterialController@setStatus')->middleware('log:id')->name('materialStatus');
        Route::post('/status', 'MaterialController@setStatus')->middleware('log:id')->name('materialStatus');
    });

});

//角色管理
Route::group([
    'namespace' => 'Role',
    'prefix'    => 'role',
], function () {
    Route::get('/lists', 'RoleController@roleLists')->name('roleLists');
    Route::get('/detail', 'RoleController@roleDetail')->name('roleDetail');
    Route::post('/create', 'RoleController@roleCreate')->middleware('log')->name('roleCreate');
    Route::post('/update', 'RoleController@roleUpdate')->middleware('log')->name('roleUpdate');
    Route::get('/effect', 'RoleController@roleEffect')->middleware('log:id')->name('roleEffect');
});

//邀请码管理
Route::group([
        'namespace' => 'Code',
        'prefix'    => 'code',
    ], function () {
        //自建列表
        Route::get('/lists', 'CodeController@lists')->name('codeList');
        //新增自建邀请码
        Route::post('/create', 'CodeController@createInviteCode')->middleware('log')->name('codeCreate');
        //邀请码使用记录列表
        Route::get('/lists/record', 'CodeController@codeLists')->name('codeRecordList');
        //邀请码分享列表
        Route::get('/lists/share', 'CodeController@shareCodeLists')->name('shareCodeList');
        //下载邀请码
        Route::get('/download/{id}', 'CodeController@downloadCode')->middleware('log')->name('downloadCode');
        Route::get('/copy', 'CodeController@copyState')->middleware('log')->name('copyState');

});

Route::group([
    'namespace' => 'Auth',
], function () {
    Route::get('/wechat/bind', 'WechatController@bind')->name('bindWechat');
    Route::post('/mobile/bind', 'AccountController@mobileBind')->middleware('log')->name('mobileBind');
    Route::post('/mobile/verify', 'AccountController@verifyCode')->name('verifyCode');
    Route::post('/password/update', 'AccountController@updatePassword')->middleware('log')->name('updatePassword');

    //账号详情
    Route::group([
        'prefix' => 'account',
    ],function (){
        Route::get('/detail', 'AccountController@accountDetail')->name('accountDetail');
        Route::post('/set', 'AccountController@accountSet')->middleware('log')->name('accountSet');
    });
});

Route::group([
    'namespace' => 'Partner',
    'prefix' => 'partner',
],function (){
    Route::get('/active', 'PartnerController@partnerActive')->middleware('log')->name('partnerActive');
});

Route::group([
    'namespace' => 'OpenPlatform',
    'prefix' => 'openplatform',
], function () {
    Route::group([
        'prefix' => 'applet',
    ], function () {
        // 小程序二维码
        Route::get('/qrcode', 'AppletQrcodeController@wxaQrcode')->name('qrcode');
        // 小程序码
        Route::get('/appcode', 'AppletQrcodeController@wxaCode')->name('appCode');
        // 无限制小程序码
        Route::get('/codeunlimit', 'AppletQrcodeController@wxaCodeUnlimit')->name('codeUnlimit');
        //小程序升级
        Route::post('/upgrade','AppletUpgradeController@upgrade')->middleware('check.applet.audit','log')->name('upgrade');
        //小程序详情
        Route::get('/detail','AppletUpgradeController@appletDetail')->name('appletDetail');
        //小程序降级
        Route::get('/downgrade','AppletUpgradeController@downgrade')->middleware('check.applet.audit','log')->name('downgrade');
    });
    Route::group([
        'prefix' => 'public',
    ], function () {
        Route::post('/callback', 'PublicController@fullWebPublishUtil')->name('publicCallback');
    });
    Route::group([
        'prefix' => 'openplatform',
    ], function () {
        Route::get('/check_wxa_bind', 'OpenPlatformController@checkWxaBind')->name('checkWxaBind');
        Route::post('/unbind', 'OpenPlatformController@unbind')->name('unbind');
    });
});

Route::group([
    'namespace'  => 'Admire',
    'prefix'     => 'admire'
],function () {
    Route::get('/list','AdmireController@listAdmire')->name('listAdmire');
    Route::get('/total','AdmireController@totalAdmire')->name('totalAdmire');
    Route::get('/detail','AdmireController@detail')->name('detailAdmire');

});


//会员卡
Route::group([
    'namespace'  => 'MemberCard',
    'prefix'     => 'member'
],function () {
    //会员卡创建
    Route::post('/card/create','MemberCardController@cardCreate')->name('cardCreate');
    //会员卡编辑
    Route::post('/card/update','MemberCardController@cardUpdate')->name('cardUpdate');
    //会员卡删除
    Route::delete('/card/delete/{id}','MemberCardController@cardDelete')->name('cardDelete');
    //会员卡上下架
    Route::get('/card/status','MemberCardController@changeState')->name('changeState');
    //会员卡详情
    Route::get('/card/detail','MemberCardController@cardDetail')->name('cardDetail');
    //会员卡订购记录
    Route::get('/card/record','MemberCardController@recordLists')->name('recordLists');
    //会员卡列表
    Route::get('/card/lists','MemberCardController@cardLists')->name('cardLists');
    //用户的会员卡列表
    Route::get('/card/user','MemberCardController@cardUser')->name('cardUser');
    //会员卡置顶
    Route::get('/card/top','MemberCardController@cardTop')->name('cardTop');
    //会员卡排序
    Route::get('/card/sort','MemberCardController@cardSort')->middleware('log')->name('cardSort');
});

Route::group([
   'namespace' => 'LimitPurchase',
    'prefix'   => 'limit'
],function (){
    Route::post('/purchase/create','LimitPurchaseController@create')->name('purchaseCreate');
    Route::post('/purchase/update','LimitPurchaseController@update')->name('purchaseUpdate');
    Route::post('/purchase/update/time','LimitPurchaseController@updateTime')->name('purchasepdateTime');
    Route::get('/purchase/lists','LimitPurchaseController@lists')->name('purchaseLists');
    Route::get('/purchase/detail/{id}','LimitPurchaseController@detail')->name('purchaseDetail');
    Route::get('/purchase/record/','LimitPurchaseController@recordLists')->name('recordLists');
    Route::get('/purchase/changer/{id}','LimitPurchaseController@changer')->name('purchaseChanger');
    Route::delete('/purchase/delete/{id}','LimitPurchaseController@delete')->name('purchaseDelete');
    Route::get('/purchase/top','LimitPurchaseController@top')->name('purchaseTop');
    Route::get('/purchase/sort','LimitPurchaseController@sort')->name('purchaseSort');
    Route::get('/purchase/analysis','LimitPurchaseController@analysis')->name('purchaseAnalysis');
});


//会员卡
Route::group([
    'namespace'  => 'User',
    'prefix'     => 'private'
],function () {
    //创建私密账户
    Route::post('/create', 'UserController@setPrivateUser')->middleware('log')->name('setPrivateUser');
    //批量创建私密账户
    Route::post('/create/mulit', 'UserController@setPrivateUserMulit')->middleware('log')->name('setPrivateUserMulit');
    //文件导入创建私密账户
    Route::post('/create/import', 'UserController@importPrivateUser')->middleware('log')->name('importPrivateUser');
    //设置店铺为私密店铺
    Route::patch('/status', 'UserController@setShopPrivate')->middleware('log')->name('setShopPrivate');
    //获取店铺是否设置私密账号
    Route::get('/status', 'UserController@getShopPrivate')->name('getShopPrivate');
    //设置私密会员设置
    Route::post('/settings', 'UserController@setPrivateSettings')->name('setPrivateSettings');
});

Route::group([
   'namespace' => 'Community',
   'prefix' => 'community',
],function (){
    Route::post('/create','CommunityController@create')->name('communityCreate');
    Route::post('/update','CommunityController@update')->name('communityUpdate');
    Route::get('/lists','CommunityController@lists')->name('communityLists');
    Route::get('/detail/{id}','CommunityController@detail')->name('communityDetail');
    Route::get('/display/{id}','CommunityController@display')->name('communityDisplay');
    Route::delete('/delete/{id}','CommunityController@delete')->name('communityDelete');
    Route::group([
        'prefix' => 'notice',
    ], function () {
        Route::post('/create', 'CommunityNoticeController@create')->name('communityNoticeCreate');
        Route::post('/update', 'CommunityNoticeController@update')->name('communityNoticeUpdate');
        Route::get('/lists', 'CommunityNoticeController@lists')->name('communityNoticeLists');
        Route::get('/detail', 'CommunityNoticeController@detail')->name('communityNoticeDetail');
        Route::get('/top', 'CommunityNoticeController@top')->name('communityNoticeTop');
        Route::get('/display', 'CommunityNoticeController@display')->name('communityNoticeDisplay');
        Route::get('/delete', 'CommunityNoticeController@delete')->name('communityNoticeDelete');
    });
    Route::group([
        'prefix' => 'note',
    ], function () {
        Route::post('/create', 'CommunityNoteController@create')->name('communityNoteCreate');
        Route::post('/update', 'CommunityNoteController@update')->name('communityNoteUpdate');
        Route::get('/lists', 'CommunityNoteController@lists')->name('communityNoteLists');
        Route::get('/detail', 'CommunityNoteController@detail')->name('communityNoteDetail');
        Route::get('/top', 'CommunityNoteController@top')->name('communityNoteTop');
        Route::get('/display', 'CommunityNoteController@display')->name('communityNoteDisplay');
        Route::get('/boutique', 'CommunityNoteController@boutique')->name('communityNoteBoutique');
        Route::get('/delete', 'CommunityNoteController@delete')->name('communityNoteDelete');
    });
    Route::group([
       'prefix' => 'member',
    ], function (){
        Route::get('lists','CommunityMemberController@lists')->name('communityMemberLists');
        Route::get('role','CommunityMemberController@role')->name('communityMemberRole');
        Route::get('gag','CommunityMemberController@gag')->name('communityMemberGag');
        Route::get('delete','CommunityMemberController@delete')->name('communityMemberDelete');
    });
});


Route::group([
    'namespace'  => 'Promotion',
    'middleware' => 'promotion.shop',
    'prefix'     => 'promotion'
],function () {
    Route::get('/set','IndexController@setPromotion')->name('setPromotion');
    Route::get('/check/list','IndexController@checkList')->name('checkList');
    Route::get('/check','IndexController@check')->middleware('log')->name('check');
    Route::get('/list','IndexController@listPromotion')->name('listPromotion');
    Route::get('/delete','IndexController@deletePromotion')->middleware('log:promotion_id')->name('deletePromotion');
    Route::get('/active','IndexController@activePromotion')->name('activePromotion');
    Route::post('/content/set','ContentController@setContent')->middleware('log')->name('setContent');
    Route::get('/content/list','ContentController@listContent')->name('listContent');
    Route::post('/content/percent','ContentController@percentContent')->middleware('log')->name('percentContent');
    Route::get('/content/delete','ContentController@deleteContent')->middleware('log')->name('deleteContent');
    Route::get('/content/all','ContentController@allContents')->name('allContents');
    Route::get('/content/single','ContentController@getPercent')->name('getPercent');
    Route::get('/record/list','IndexController@recordPromotion')->name('recordPromotion');
    Route::get('/record/excel','IndexController@recordExcel')->name('recordExcel');
    Route::get('/record/total','IndexController@recordTotal')->name('recordTotal');
    Route::get('/total/excel','IndexController@totalExcel')->name('totalExcel');
    Route::group([
        'prefix'     => 'set',
//        'middleware' => 'promotion.shop'
    ],function () {
        Route::get('/check','SetController@setChech')->middleware('log')->name('setChech');
        Route::post('/percent','SetController@setPercent')->middleware('log')->name('setPercent');
        Route::post('/plan/update','SetController@planCreateOrUpdate')->middleware('log')->name('planCreateOrUpdate');
        Route::get('/plan/detail','SetController@planDetail')->name('planDetail');
        Route::get('/detail','SetController@detailPercent')->name('detailPercent');
        Route::get('/status','SetController@setPromotionStatus')->middleware('log')->name('setPromotionStatus');
        Route::get('/get/status','SetController@checkPromotionStatus')->name('checkPromotionStatus');
    });
});

Route::group([
    'namespace'  => 'Auth',
    'prefix'     => 'xiuzan',
],function () {
    //获取短书登录秀赞签名数据
    Route::get('/sign','XiuzanController@xzLogin')->middleware('log')->name('xzLogin');
    Route::get('/feedback/list','XiuzanController@xzLogin')->middleware('log')->name('xzLogin');
});


Route::group([
    'namespace'  => 'Xiuzan',
    'prefix'     => 'xiuzan',
],function () {
    //获取短书登录秀赞签名数据
    Route::get('/feedback/list','FeedbackController@Lists')->name('xiuzanFeedbackList');
    Route::get('/survey/list','SurveyController@Lists')->name('xiuzanFeedbackList');
});

Route::group([
    'namespace'  => 'Cache',
    'prefix'     => 'sync',
],function () {
    //邀请推广记录处理成两条脚本
    Route::get('/promotion/record','SyncController@syncPromotionRecord')->middleware('log')->name('syncPromotionRecord');
});

//小程序信息
Route::group([
    'namespace'  => 'OpenPlatform',
    'prefix'     => 'wxApplet'
], function () {
    Route::get('/check/bind', 'WXAppletController@checkBind')->name('WXAppletCheckBind');
    Route::get('/check/submitaudit', 'WXAppletController@checkSubmitAudit')->name('WXAppletCheckSubmitAudit');
    Route::get('/check/release', 'WXAppletController@checkRelease')->name('WXAppletCheckRelease');
    Route::get('/check/commit', 'WXAppletController@checkCommit')->name('WXAppletCheckCommit');
    Route::get('/unlimit/qrcode', 'AppletQrcodeController@getUnlimitQrcode')->name('WXAppletUnlimitQrcode'); //圆形无限制

});
