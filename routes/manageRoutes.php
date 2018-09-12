<?php
//内容管理
Route::group([
    'namespace' => 'Content',
    'prefix'    => 'content',
], function () {
    //专栏列表
    Route::get('/lists/column', 'ContentController@columnList')->name('contentColumnList');
    //专栏详情
    Route::get('/detail/column','ContentController@columnDetail')->name('columnDetail');
    //专栏下的内容列表
    Route::get('/column', 'ContentController@getContentByColumnId')->name('columnContent');
    //新增统计
    Route::get('/analysis/add', 'ContentController@analysisAddContent')->name('analysisAddContent');
    //增长分析
    Route::get('/analysis/increase', 'ContentController@increaseAnalysis')->name('increaseAnalysis');
    //内容详情
    Route::get('/detail', 'ContentController@detail')->name('contentDetail');
    //内容列表
    Route::get('/list', 'ContentController@listContent')->name('listContent');
    //内容上下架
    Route::get('/state', 'ContentController@changeState')->middleware('admin.logs')->name('contentState');
    //内容锁定
    Route::get('/lock', 'ContentController@contentLock')->middleware('admin.logs')->name('contentLock');
    //内容显示隐藏
    Route::get('display', 'ContentController@contentDisplay')->middleware('admin.logs')->name('contentDisplay');
    //内容下店铺黑名单管理
    Route::get('/shop/black', 'ContentController@shopBlackByContent')->middleware('admin.logs')->name('shopBlackByContent');
    //单个章节下的所有课时
    Route::get('/chapter/detail', 'ContentController@detailChapter')->name('detailChapter');
    //课时详情
    Route::get('/class/detail','ContentController@detailClass')->name('detailClass');
    //商店下的内容
    Route::get('/shop/list','ContentController@shopContent')->name('shopListContent');
    Route::get('/lists/live', 'ContentController@getLiveMessage')->name('getLiveMessage');
});

//素材管理
Route::group([
    'namespace' => 'Material',
    'prefix'    => 'material'
], function () {
    //素材列表
    Route::get('/lists', 'MaterialController@lists')->name('materialList');
    //素材删除
    Route::get('/delete', 'MaterialController@deleteMaterial')->middleware('admin.logs')->name('deleteMaterial');
    //素材不合法图片替换
    Route::get('/replace', 'MaterialController@illegalReplace')->middleware('admin.logs')->name('illegalReplace');
    Route::post('/font/upload', 'MaterialController@fontUpload')->name('fontUpload');

});

//订单管理
Route::group([
    'namespace' => 'Financial',
    'prefix'    => 'order',
], function () {
    Route::get('/lists', 'OrderController@lists')->name('contentList');
    //收入统计
    Route::get('/count', 'OrderController@orderIncome')->name('orderIncome');
    // 会员订单
    Route::get('/member', 'OrderController@getMemberOrder')->name('orderMember');
});

//会员管理
Route::group([
    'namespace' => 'Member',
    'prefix'    => 'member',
], function () {
    // 会员列表
    Route::get('/lists', 'MemberController@lists')->name('memberList');
    // 会员详情
    Route::get('/detail/{id}', 'MemberController@detail')->name('mebmerDetail');
    //会员黑名单管理
    Route::get('/black', 'MemberController@memberBlack')->middleware('admin.logs')->name('memberBlack');
    // 会员TOP100
    Route::get('/top', 'AnalysisController@memberTop')->name('memberTop');
    // 会员内容查看权限设置
    Route::get('/auth', 'MemberController@memberAuth')->middleware('admin.logs')->name('memberAuth');
});

//店铺管理
Route::group([
    'namespace' => 'Shop',
    'prefix'    => 'shop',
], function () {
    //商铺列表
    Route::get('/lists', 'ShopController@lists')->name('shopList');
    //增长统计
    Route::get('/count', 'ShopController@shopCount')->name('shopCount');
    // 用户店铺列表
    Route::get('/user/lists', 'ShopController@userLists')->name('userShopLists');
    // 修改店铺状态
    Route::get('/status', 'ShopController@chgStatus')->middleware('admin.logs')->name('shopStatus');
    // 修改店铺参数
    Route::get('/update', 'ShopController@update')->middleware('admin.logs')->name('shopUpdate');
    //店铺所有管理者
    Route::get('/info', 'ShopController@getshopInfoBySid')->name('shopInfo');
    //店铺导航分类
    Route::get('/type', 'TypeController@shopType')->name('shopType');
    //店铺导航状态修改
    Route::get('/type/status', 'TypeController@changeState')->middleware('admin.logs')->name('changeTypeState');
    //店铺分享信息
    Route::get('/share', 'ShareController@shopShare')->name('shopShare');
    //店铺授权信息展示开关
    Route::get('/check/copyright/show', 'ShareController@checkCopyrightShow')->name('checkCopyrightShow');
    //店铺详情
    Route::get('/detail', 'ShopController@shopDetail')->name('shopDetail');
    //商铺黑名单
    Route::get('/black', 'ShopController@shopBlack')->middleware('admin.logs')->name('shopBlack');
    //提现
    Route::get('/cash','ShopController@getCash')->name('getCash');
    //店铺倍数
    Route::post('/multiple', 'ShopController@multiple')->middleware('admin.logs')->name('shopMultiple');
    Route::get('/message','MessageController@lists')->name('messageLists');
    Route::get('/user/message','MessageController@userMessage')->name('userMessage');

    //帮助中心
    Route::group([
        'prefix' => 'help',
    ],function (){
        Route::post('/create', 'HelpCenterController@create')->name('helpCreate');
        Route::put('/update/{id}', 'HelpCenterController@update')->name('helpUpdate');
        Route::delete('/delete/{id}', 'HelpCenterController@delete')->name('helpDelete');
        Route::get('/list', 'HelpCenterController@getList')->name('helpList');
        Route::get('/detail/{id}', 'HelpCenterController@detail')->name('helpDetail');
        Route::get('/display/{id}', 'HelpCenterController@isDisplay')->name('helpDisplay');
        Route::post('/sort', 'HelpCenterController@sort')->name('helpSort');
    });
    //7天高级版体验权限
    Route::post('/seven/perm','ShopController@sevenPerm')->name('sevenPerm');
    Route::post('/version/probation','ShopController@versionProbation')->name('versionProbation');
});

//用户
Route::group([
    'namespace' => 'User',
    'prefix'    => 'user',
], function () {
    // 用户列表
    Route::get('/lists', 'UserController@lists')->name('userList');
    // 用户详情
    Route::get('/detail/{id}', 'UserController@detail')->name('userDetail');
    // 用户参数修改
    Route::post('/update', 'UserController@update')->middleware('admin.logs')->name('userUpdate');
    // 用户黑名单管理
    Route::get('/black', 'UserController@userBlack')->middleware('admin.logs')->name('userBlack');
    // 试用用户列表
    Route::get('/try/lists', 'UserController@tryUserLists')->name('tryUserLists');
    //高级版用户列表
    Route::get('/advanced/lists', 'UserController@advancedUserList')->name('advancedUserList');
    //高级版用户购买详情
    Route::get('/advanced/detail', 'UserController@getShopVersionDetail')->name('getShopVersionDetail');
    //活跃用户列表
    Route::get('/active/list', 'UserController@activeUserList')->name('activeUserList');
    //用户点击按钮记录
    Route::get('/button/click', 'UserController@userButtonClickList')->name('userButtonClickList');
    //用户点击按钮记录
    Route::get('/lists/pay', 'UserController@todayPayUser')->name('todayPayUser');

    //获取管理员等级
    Route::get('/level', 'UserController@level')->name('userLevel');
    Route::get('/advanced/export', 'UserController@advancedUserExport')->name('advancedUserExport');
});

// 日志
Route::group([
    'namespace' => 'Logs',
    'prefix'    => 'logs',
], function () {
    // 日志列表
    Route::get('/lists', 'LogsController@lists')->name('logsList');
    // 日志详情
    Route::get('/detail', 'LogsController@detail')->name('logsDetail');
    // 错误日志列表
    Route::get('/error/lists', 'ErrorLogsController@errorLists')->name('errorLogsLists');
    // 错误日志详情
    Route::get('/error/detail', 'ErrorLogsController@errorDetail')->name('errorLogsDetail');
    // H5日志列表
    Route::get('/H5/lists', 'H5LogsController@H5Lists')->name('H5LogsLists');
    // H5日志详情
    Route::get('/H5/detail', 'H5LogsController@H5Detail')->name('H5LogsDetail');
    // Curl日志列表
    Route::get('/curl/lists', 'CurlLogsController@curlLists')->name('curlLogsLists');
    // Curl日志详情
    Route::get('/curl/detail', 'CurlLogsController@curlDetail')->name('curlLogsDetail');
    //运营后台操作日志列表
    Route::get('/admin/lists', 'LogsController@adminLogsList')->name('adminLogsList');
    //运营后台操作日志详情
    Route::get('admin/detail', 'LogsController@adminLogsDetail')->name('adminLogsDetail');
    Route::get('fixed', 'ErrorLogsController@fixed')->name('fixedErrorLog');
});

//内容分析
Route::group([
    'prefix' => 'analysis',
], function () {
    Route::group([
        'namespace' => 'User',
        'prefix'    => 'user'
    ], function () {
        Route::get('/total', 'AnalysisController@userTotal')->name('countUserTotal');
        Route::get('/chart', 'AnalysisController@userDataChart')->name('userChart');

        //测试跑脚本
        Route::get('/test', 'AnalysisController@test')->name('test');
    });
    Route::group([
        'namespace' => 'Member',
        'prefix'    => 'member'
    ], function () {
        Route::get('/total', 'AnalysisController@memberTotal')->name('countMemberTotal');
        Route::get('/chart', 'AnalysisController@memberDataChart')->name('memberChart');
        Route::get('/distribute', 'AnalysisController@memberDistribute')->name('countMemberDistribute');
    });
    Route::group([
        'namespace' => 'Content',
        'prefix'    => 'content'
    ], function () {
        Route::get('/total', 'AnalysisController@contentTotal')->name('countContentTotal');
        Route::get('/chart', 'AnalysisController@contentDataChart')->name('countContentChart');
        //单个内容统计分析
        Route::get('/single', 'SingleController@SingleAnalysis')->name('SingleAnalysis');
    });
    Route::group([
        'namespace' => 'Financial',
        'prefix'    => 'financial'
    ], function () {
        Route::get('/total', 'AnalysisController@financialTotal')->name('countFinancialTotal');
        Route::get('/chart', 'AnalysisController@financialDataChart')->name('countFinancialChart');
        Route::get('/percent', 'AnalysisController@financialPercent')->name('countFinancialPercent');
        Route::get('/high/content', 'AnalysisController@highContent')->name('countFinancialHigh');
    });
});

// 评论
Route::group([
    'namespace' => 'Comment',
    'prefix'    => 'comment'
], function () {
    // 评论列表
    Route::get('/lists', 'CommentController@comments')->name('commentLists');
    // 回复消息列表
    Route::get('/reply', 'CommentController@replyList')->name('replyList');
    // 评论隐藏显示
    Route::get('/status', 'CommentController@changeStatus')->middleware('admin.logs')->name('commentStatus');
});

// 反馈
Route::group([
    'namespace' => 'Feedback',
    'prefix'    => 'feedback'
], function () {
    // 反馈列表
    Route::get('/lists', 'FeedbackController@feedback')->name('feedbackLists');
});

Route::group([
    'namespace' => 'Dashboard',
    'prefix'    => 'dashboard'
], function () {
    // 今日概况
    Route::get('/situation', 'DashboardController@todaySituation')->name('DashboardSituation');
    // 收入概要
    Route::get('/incomeTotal', 'DashboardController@incomeTotal')->name('DashboardIncomeTotal');
    //官网概况
    Route::get('/website/lists', 'DashboardController@websiteSituationLists')->name('WebsiteSituationLists');
});

Route::group([
    'namespace' => 'Partner',
    'prefix'    => 'partner'
], function () {
    // 合伙人列表
    Route::get('/lists', 'PartnerController@lists')->name('partnerLists');
    // 合伙人详情
    Route::get('/detail/{id}', 'PartnerController@detail')->name('partnerDetail');
    // 修改合伙人状态
    Route::post('/state', 'PartnerController@chgState')->middleware('admin.logs')->name('partnerChgState');
});

//消息
Route::group([
    'namespace' => 'Notice',
    'prefix'    => 'notice'
], function () {
    //新增系统通知
    Route::post('/system/create', 'NoticeController@systemCreate')->middleware('admin.logs')->name('systemCreate');
    Route::post('/system/update', 'NoticeController@systemUpdate')->middleware('admin.logs')->name('systemUpdate');
    //系统通知列表
    Route::get('/system/lists', 'NoticeController@lists')->name('systemLists');
    //系统通知详情
    Route::get('/system/detail/{id}', 'NoticeController@systemDetail')->name('systemDetail');
    //系统消息删除
    Route::get('/system/delete', 'NoticeController@delete')->middleware('admin.logs')->name('systemDelete');
    //会员信息列表
    Route::get('/member/lists', 'MemberNoticeController@lists')->name('memberNotice');
    //会员消息删除
    Route::get('/member/delete', 'MemberNoticeController@delete')->middleware('admin.logs')->name('memberDelete');
    //小程序通知新建
    Route::post('/applet/create', 'AppletNoticeController@appletNoticeCreate')->middleware('admin.logs')->name('appletNoticeCreate');
    Route::post('/applet/update', 'AppletNoticeController@appletNoticeUpdate')->middleware('admin.logs')->name('appletNoticeUpdate');
    //小程序通知列表
    Route::get('/applet/lists', 'AppletNoticeController@appletNoticeLists')->name('appletNoticeLists');
    //小程序通知详情
    Route::get('/applet/detail/{id}', 'AppletNoticeController@appletNoticeDetail')->name('appletNoticeDetail');
    Route::get('/applet/delete/', 'AppletNoticeController@appletNoticeDelete')->name('appletNoticeDelete');

});

//赠送码
Route::group([
    'namespace' => 'Code',
    'prefix'    => 'code'
], function () {
    //自建邀请码列表
    Route::get('/self/list', 'CodeController@selfCodeList')->name('selfcode');
    //自建invite_id对应下的所有code详情（self）
    Route::get('/self/detail', 'CodeController@selfCodeDetail')->name('selfCodeDetail');
    //所有分享码详情（share）
    Route::get('/share/list', 'CodeController@shareCodeList')->name('shareCodeList');
});

//商铺轮播图
Route::group([
    'namespace' => 'Banner',
    'prefix'    => 'banner'
], function () {
    //商铺banner列表
    Route::get('/list', 'BannerController@bannerList')->name('bannerList');
    //商铺banner上下架
    Route::get('/state', 'BannerController@bannerState')->middleware('admin.logs')->name('bannerState');
    //商铺banner是否锁定
    Route::get('/lock', 'BannerController@bannerLock')->middleware('admin.logs')->name('bannerLock');
});

//邀请卡
Route::group([
    'namespace' => 'Code',
    'prefix'    => 'card'
], function () {
    //新增模板
    Route::post('/save', 'CardController@saveTemplate')->middleware('admin.logs')->name('saveTemplate');
    //模板详情
    Route::get('/detail', 'CardController@getDetail')->name('detailTemplate');
    //更新模板
    Route::post('/update', 'CardController@updateTemplate')->middleware('admin.logs')->name('updateTemplate');
    //删除模板
    Route::get('/delete', 'CardController@deleteTemplate')->middleware('admin.logs')->name('deleteTemplate');
    //更改显示隐藏状态
    Route::get('/status','CardController@changeStatus')->middleware('admin.logs')->name('changeCardStatus');
    //模板排序
    Route::get('/order','CardController@templateOrder')->middleware('admin.logs')->name('templateOrder');
    //列表
    Route::get('/lists', 'CardController@lists')->name('manageTemplateLists');
});

//短书平台
Route::group([
    'namespace' => 'OpenPlatform',
    'prefix'    => 'official'
], function () {
    //小程序列表
    Route::get('/applet/authorized/lists', 'OpenPlatformController@appletAuthorizedLists')->middleware('admin.logs')->name('appletAuthorizedLists');
    //小程序详情
    Route::get('/applet/authorized/detail', 'OpenPlatformController@appletAuthorizedDetail')->middleware('admin.logs')->name('appletAuthorizedDetail');
    //公众号列表
    Route::get('/public/authorized/lists', 'OpenPlatformController@publicAuthorizedLists')->middleware('admin.logs')->name('publicAuthorizedLists');
    //公众号详情
    Route::get('/public/authorized/detail', 'OpenPlatformController@publicAuthorizedDetail')->middleware('admin.logs')->name('publicAuthorizedDetail');
    //授权方代码生成列表
    Route::get('/commit/lists', 'OpenPlatformController@commitLists')->middleware('admin.logs')->name('commitLists');
    //授权方代码生成详情
    Route::get('/commit/detail', 'OpenPlatformController@commitDetail')->middleware('admin.logs')->name('commitDetail');
    //授权方代码审核列表
    Route::get('/submitAudit/lists', 'OpenPlatformController@submitAuditLists')->middleware('admin.logs')->name('submitAuditLists');
    //授权方代码审核详情
    Route::get('/submitAudit/detail', 'OpenPlatformController@submitAuditDetail')->middleware('admin.logs')->name('submitAuditDetail');
    //小程序模板列表
    Route::get('/appletTemplate/lists', 'OpenPlatformController@appletTemplateLists')->middleware('admin.logs')->name('appletTemplateLists');
    //新增小程序模板
    Route::post('/appletTemplate/create', 'OpenPlatformController@appletTemplateCreate')->middleware('admin.logs')->name('appletTemplateCreate');
    //修改小程序模板
    Route::post('/appletTemplate/update', 'OpenPlatformController@appletTemplateUpdate')->middleware('admin.logs')->name('appletTemplateUpdate');
    //删除小程序模板
    Route::delete('/appletTemplate/delete', 'OpenPlatformController@appletTemplateDelete')->middleware('admin.logs')->name('appletTemplateDelete');
    //颜色模板列表
    Route::get('/colorTemplate/lists', 'ColorTemplateController@lists')->middleware('admin.logs')->name('colorTemplateLists');
    //新增颜色模板
    Route::post('/colorTemplate/create', 'ColorTemplateController@create')->middleware('admin.logs')->name('colorTemplateCreate');
    //修改颜色模板
    Route::post('/colorTemplate/update', 'ColorTemplateController@update')->middleware('admin.logs')->name('colorTemplateUpdate');
    //删除颜色模板
    Route::delete('/colorTemplate/delete', 'ColorTemplateController@delete')->middleware('admin.logs')->name('colorTemplateDelete');
});

//小程序打包
Route::group([
    'namespace'  => 'Shop',
    'prefix'     => 'applet'
],function () {
    //商铺banner列表
    Route::get('/token','AppletController@getToken')->name('getAppletToken');
    //上传小程序zip文件
//    Route::post('/upload','AppletController@appletUpload')->name('getAppletUpload');
    //替换下载小程序zip文件
    Route::get('/download', 'AppletController@appletDownload')->name('getAppletDownload');
});

//赞赏
Route::group([
    'namespace'  => 'Admire',
    'prefix'     => 'admire'
],function () {
    Route::get('list','AdmireController@listAdmire')->name('listAdmire');
    Route::get('detail','AdmireController@detailAdmire')->name('detailAdmire');
    Route::get('total','AdmireController@totalAdmire')->name('totalAdmire');
});

//配色
Route::group([
    'namespace'  => 'Shop',
    'prefix'     => 'color'
],function () {
    Route::post('create/update','ColorController@createOrUpdate')->name('createOrUpdateColor');
    Route::get('lists','ColorController@lists')->name('colorList');
    Route::get('delete','ColorController@delete')->name('colorDelete');
    Route::get('order','ColorController@colorOrder')->name('colorOrder');
});

//排行榜
Route::group([
    'namespace' => 'Financial',
    'prefix'    => 'top',
], function () {
    Route::get('/shop', 'OrderController@topShop')->name('topShop');
    Route::get('/subscribe', 'OrderController@topSubscribe')->name('topSubscribe');
    Route::get('/view', 'OrderController@topViewCount')->name('topViewCount');
    Route::get('/share', 'OrderController@topShare')->name('topShare');
});
//配色
Route::group([
    'namespace'  => 'Shop',
    'prefix'     => 'postage'
],function () {
    Route::get('get','ShopController@postage')->name('getPostage');
    Route::post('save','ShopController@savePostage')->name('savePostage');
});


//会员卡
Route::group([
    'namespace' => 'MemberCard',
    'prefix'    => 'member/card',
], function () {
    Route::get('/lists', 'MemberCardController@cardLists')->name('cardLists');
    Route::get('/detail', 'MemberCardController@cardDetail')->name('cardDetail');
    Route::get('/record', 'MemberCardController@recordLists')->name('recordLists');
});



//会员卡
Route::group([
    'namespace' => 'Customer',
    'prefix'    => 'customer',
], function () {
    Route::get('/public/lists', 'CustomerController@publicPool')->name('publicPool');
    Route::get('/private/lists', 'CustomerController@privatePool')->name('privatePool');
    Route::post('/private/add', 'CustomerController@addPublicToPrivate')->name('addPublicToPrivate');
    Route::post('/private/remove', 'CustomerController@removeFormPrivate')->name('removeFormPrivate');
    Route::post('/public/add', 'CustomerController@addNewToPublic')->name('addNewToPublic');
    Route::post('/update', 'CustomerController@updateCustomer')->name('updateCustomer');
    Route::post('/intention/add', 'CustomerController@addIntention')->name('addIntention');
    Route::post('/intention/delete', 'CustomerController@deleteIntention')->name('deleteIntention');
    Route::get('/single', 'CustomerController@oneCustomer')->name('oneCustomer');

    Route::get('/follow/lists', 'FollowController@followLog')->name('followLog');
    Route::post('/follow/add', 'FollowController@addFollowLog')->name('addFollowLog');
    Route::post('/follow/update', 'FollowController@updateFollowLog')->name('updateFollowLog');
    Route::get('/follow/delete', 'FollowController@deleteFollwLog')->name('deleteFollwLog');
    Route::post('/follow/book', 'FollowController@bookFollowTime')->name('bookFollowTime');

    Route::get('/sysnc', 'CustomerController@sysncCustomer')->name('sysncCustomer');
    Route::get('/manage/lists', 'ManageController@publicPool')->name('publicPool');
    Route::post('/private/change', 'ManageController@changeCustomerFollow')->name('changeCustomerFollow');
    //更改微信跟进情况状态
    Route::get('/wechat/change', 'CustomerController@changeWechat')->name('changeWechat');
});