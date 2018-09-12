<?php
//内容管理
Route::group([
    'namespace' => 'Content',
    'prefix'    => 'content',
], function () {
    Route::get('column/lists', 'ContentController@columnLists')->name('columnList');
    Route::get('alive/lists', 'ContentController@aliveLists')->name('aliveList');
    Route::get('lists', 'ContentController@lists')->name('contentList');
    Route::get('column/contents', 'ContentController@contents')->name('contents');
    Route::get('column/detail/{id}', 'ContentController@columnDetail')->middleware('check.column.pay')->name('columnDetail');
    Route::get('/detail/{id}', 'ContentController@detail')
        ->middleware('pay.check','h5.log')
        ->name('contentDetail');
    Route::get('/free/column/detail/{id}', 'ContentController@freeColumnDetail')->name('columnDetail');
    Route::get('/free/detail/{id}', 'ContentController@freeDetail')->name('contentDetail');
    Route::get('/user/column', 'ContentController@subscribeColumnList')->name('subscribeColumnList');
    Route::get('/user/subscribe', 'ContentController@subscribeFreeColumn')->name('subscribeFreeColumn');
    Route::get('/common/subscribe', 'ContentController@subScribeFreeCommonContent')->name('subscribeCommonContent');
    Route::get('/play/count', 'ContentController@playCount')->middleware('h5.log')->name('playCount');
    Route::get('/share/count', 'ContentController@shareCount')->middleware('h5.log')->name('shareCount');
    //课程
    Route::get('/course/lists', 'CourseController@lists')->name('courseLists');
    Route::get('/course/detail/{id}', 'CourseController@detail')->middleware('course.check.pay')->name('courseDetail');
    Route::get('/course/free/detail/{id}', 'CourseController@freeDetail')->name('courseFreeDetail');
    Route::get('/course/chapter/lists', 'CourseController@chapterList')->name('chapterList');
    Route::get('/course/class/view/count', 'CourseController@view_count')->name('classViewCount');
    //课时详情
    Route::get('/course/class/detail', 'CourseController@classDetail')->middleware('course.check.pay')->name('classDetail');
    Route::get('/course/subscribe/lists', 'CourseController@subscribeCourseList')->name('subscribeCourseList');
    Route::get('/course/subscribe', 'CourseController@subscribeFreeCourse')->name('subscribeFreeCourse');
    // 课程学习资料
    Route::get('/course/{course_id}/materials', 'CourseMaterialController@list')->name('courseMaterialList');
    Route::get('/course/{course_id}/materials/{id}', 'CourseMaterialController@detail')->name('courseMaterialDetail');

    //课程目录结构
    Route::get('/course/{course_id}/struct', 'CourseStructController@struct')->name('courseStruct');

    //课程学生
    Route::get('/course/{course_id}/students', 'CourseStudentController@list')->name('CourseStudentList');



    Route::get('/type','ContentController@getTypeContentNew')->name('getContentByType');
//    Route::get('/type','ContentController@getContentByType')->name('getContentByType');
    //首页列表整合
    Route::post('/multi/lists','MultiTypeListController@multiTypeList')->name('multiTypeList');
    Route::get('/component/contents','ComponentContentsController@ComponentContentsList')->name('ComponentContentsList');

    //推送提醒
    Route::post('/remind/open', 'PushRemindController@open')->name('remindOpen');
    Route::delete('/remind/close', 'PushRemindController@close')->name('remindClose');
    Route::get('/remind/list', 'PushRemindController@display')->name('remindDisplay');
    Route::post('/remind/auth/url', 'PushRemindController@getAuthUrl')->name('getAuthUrl');
    Route::get('/remind/save/form_id', 'PushRemindController@saveFormId')->name('saveFormId');

});

//评论管理
Route::group([
    'namespace' => 'Content',
    'prefix'    => 'alive',
], function () {
    //直播消息列表
    Route::get('/message/lists', 'AliveController@messageLists')->name('messageLists');
    Route::get('/round/lists', 'AliveController@roundLists')->name('roundLists');
    //直播消息发送
    Route::post('/message/send', 'AliveController@messageSend')->middleware('h5.log')->name('messageSend');
    //直播在线人数
    Route::get('/online/people', 'AliveController@onlinePeople')->name('onlinePeople');
    //在线人数返回
    Route::get('/online/count', 'AliveController@onlineCount')->name('onlineCount');
    //直播消息撤回(会员操作)
    Route::get('/message/revoke', 'AliveController@messageRevoke')->middleware('h5.log')->name('messageRevoke');
    //直播消息删除(管理操作)
    Route::get('/message/delete', 'AliveController@messageDelete')->middleware('h5.log')->name('messageDelete');
    //直播禁言(分全体和个人)
    Route::get('/message/gag', 'AliveController@messageGag')->middleware('h5.log')->name('messageGag');
    //结束直播
    Route::get('/end', 'AliveController@endAlive')->middleware('h5.log')->name('endAlive');
    //直播管理模式开关
    Route::get('/manage', 'AliveController@liveManage')->middleware('h5.log')->name('liveManage');
    Route::get('/pattern', 'AliveController@livePattern')->name('livePattern');
    Route::get('/audio/read', 'AliveController@audioRead')->name('audioRead');
    Route::post('/problem/answer', 'AliveController@problemAnswer')->middleware('h5.log')->name('problemAnswer');
    Route::get('/problem/status', 'AliveController@problemStatus')->name('problemStatus');
    //讲师输入状态
    Route::get('/input/status', 'AliveController@inputStatus')->middleware('h5.log')->name('inputStatus');
    Route::get('/message/read', 'AliveController@messageRead')->name('messageRead');
});


Route::group([
    'namespace' => 'Comment',
    'prefix'    => 'comment',
], function () {
    //评论相关数据
    Route::get('/lists', 'CommentController@lists')->name('commentList');
    //进行评论或者回复评论
    Route::post('/add', 'CommentController@addComment')->middleware('h5.log')->name('commentReply');
    //进行点赞
    Route::get('/praise', 'CommentController@praise')->middleware('h5.log')->name('commentPraise');
    //删除评论
    Route::get('/delete', 'CommentController@deleteComment')->middleware('h5.log')->name('commentDelete');
    //向上或向下查询的评论列表
    Route::get('/limit/lists', 'CommentController@limitLists')->name('commentLimitList');
    Route::get('/simple_lists', 'CommentController@simpleList')->name('commentSimpleList');
    Route::get('/star', 'CommentStarController@star')->name('commentStar');
});

//消息管理
Route::group([
    'namespace' => 'Notice',
    'prefix'    => 'notice',
], function () {
    Route::get('/lists', 'NoticeController@lists')->name('noticeList');
    Route::get('/{id}', 'NoticeController@detail')->name('noticeDetail')->where(['id'=>'[0-9]+']);
    // 忽略消息
    Route::post('/{id}/ignore', 'NoticeController@ignoreNotice')->name('ignoreNotice')->where(['id' => '[0-9]+']);
    Route::get('/interact', 'NoticeController@interactNotify')->name('interactNotify');
    Route::get('/unread/number', 'NoticeController@unreadNumber')->name('unreadNumber');

});

//轮转图
Route::group([
    'namespace' => 'Banner',
    'prefix'    => 'banner',
], function () {
    Route::get('/lists', 'BannerController@lists')->name('bannerList');
    Route::get('/{id}', 'BannerController@detail')->name('multiCostsLists');
});

//会员管理
Route::group([
    'namespace' => 'User',
    'prefix'    => 'user',
], function () {
    //我的信息详情
    Route::get('/detail', 'UserController@detail')->name('h5UserDetail');
    //编辑我的信息（小程序端使用，小程序不支持patch）
    Route::post('/update', 'UserController@update')->middleware('h5.log')->name('h5UserUpdate');
    //编辑我的信息
    Route::patch('/update', 'UserController@update')->middleware('h5.log')->name('h5UserUpdate');
    //手机绑定
    Route::get('/mobile/bind', 'UserController@mobileBind')->middleware('h5.log')->name('h5UserMobileBind');
    Route::get('/member/card', 'UserController@memberCard')->name('h5MemberCard');
    //修改密码
    Route::post('/password/update', 'UserController@updatePassword')->middleware('h5.log')->name('updateMemberPassword');

    //我的权益
    Route::get('/gifts', 'UserController@myPresentList')->middleware('h5.log')->name('myPresents');


});

//反馈管理
Route::group([
    'namespace' => 'Feedback',
    'prefix'    => 'feedback',
], function () {
    Route::post('/add', 'FeedbackController@addFeedback')->middleware('h5.log')->name('addFeedback');
});

//店铺管理
Route::group([
    'namespace' => 'Shop',
    'prefix'    => 'shop',
], function () {
    //导航列表
    Route::get('/navigation/lists', 'TypeController@lists')->name('navigationLists');
    Route::get('/navigation/type/{id}', 'TypeController@typeDetail')->name('navigationTypeDetail');
    Route::get('/navigation/list', 'TypeController@getList')->name('navigationList');
    //店铺分享设置
    Route::get('/share', 'ShareController@detail')->name('shareDetail');
    //店铺信息
    Route::get('/info', 'ShopController@info')->name('shopInfo');
});

//订单管理
Route::group([
    'namespace' => 'Finance',
], function () {
    Route::post('/order/make', 'OrderController@makeOrder')->middleware('shop.verify','h5.log')->name('makeOrder');
    Route::post('/order/pay/{id}', 'OrderController@goPay')->name('orderPay');
    Route::get('/order/lists', 'OrderController@lists')->name('orderLists');
    Route::get('/pay/{type}/{id}', 'PayControlller@isPay')->middleware('h5.log')->name('isPay');
    Route::post('/admire/order', 'AdmireController@admireOrder')->middleware('shop.verify','h5.log')->name('admireOrder');
    Route::post('/admire/applet_order', 'AdmireController@appletAdmireOrder')->middleware('shop.verify', 'h5.log')->name('admireAppletOrder');
    Route::get('/order/status', 'OrderController@orderStatus')->name('orderStatus');
    Route::post('/make/order', 'AppletController@orderMake')->middleware('shop.verify','h5.log')->name('orderMake');
    //重新支付
    Route::post('/order/repay', 'OrderController@repayment')->middleware('h5.log')->name('orderRepayment');
    //取消订单
    Route::post('/order/cancel', 'OrderController@cancelOrder')->middleware('h5.log')->name('cancelOrder');
    //订单详情
    Route::get('/order/{order_id}', 'OrderController@orderDetail')->name('orderDetail')->where(['order_id'=>'\w{30}']);
});
//直播素材管理
Route::group([
    'namespace' => 'Material',
    'prefix'    => 'material',
    'middleware'=> 'check.user'
], function () {

    //保存素材
    Route::post('/save', 'MaterialController@saveMaterial')->middleware('h5.log')->name('saveMaterial');
    //获取素材列表
    Route::get('/lists', 'MaterialController@lists')->name('listsMaterial');
    //删除素材
    Route::get('/delete', 'MaterialController@delete')->middleware('h5.log')->name('h5deleteMaterial');
    //更新素材
    Route::post('/update', 'MaterialController@update')->middleware('h5.log')->name('h5updateMaterial');
    //素材详情
    Route::get('/detail', 'MaterialController@detail')->name('detailMaterial');
    //上传素材
    Route::get('/upload', 'MaterialController@uploadMaterial')->middleware('h5.log')->name('uploadMaterial');
});

//邀请码管理
Route::group([
    'namespace' => 'Code',
], function () {
    //使用邀请码
    Route::get('/code/use', 'CodeController@useCode')->name('useCode');
    Route::get('/share/{code}', 'CodeController@getShareContent')->name('ShareContent')->where(['code'=>'[1-9][0-9]{15}|gc-[1-9][0-9]{15}|[1-9][0-9]{3}-[1-9][0-9]{3}-[1-9][0-9]{3}']);
    Route::get('/share/get/{code}', 'CodeController@getShare')->middleware('h5.log:code')->name('getShare');
    // 群发赠送领取
    Route::get('/qunfa_gift/{id}', 'CodeController@mygiftDetail')->middleware('h5.log:code')->name('mygiftDetail');
    Route::post('/qunfa_gift/{id}/get', 'CodeController@getQunFaGift')->middleware('h5.log:code')->name('getQunFaGift');
    Route::get('/share/callback', 'CodeController@shareCallback')->middleware('h5.log')->name('shareCallback');
    Route::get('/gift/lists', 'CodeController@myPresentation')->name('myPresentation');
    Route::post('/gift/word', 'CodeController@giftWord')->name('giftWord');
    Route::get('/card/make', 'CardController@inviteCard')->middleware('h5.log')->name('inviteCardMake');
    Route::get('applet/card/make', 'CardController@appletInviteCard')->middleware('h5.log')->name('appletInviteCard');
    Route::get('/card/lists', 'CardController@templateList')->name('templateList');
    //获取邀请卡生成所需内容信息
    Route::get('/card/content', 'CardController@inviteCardContentInfo')->name('inviteCardContentInfo');
    //生成支付卡片
    Route::post('/pay/card/make', 'CardController@payCardMark')->middleware('h5.log')->name('payCardMark');
    //生成推广卡片
    Route::post('/promotion/shop/card/make', 'CardController@promotionShopMake')->middleware('h5.log')->name('cardPromotionShopMake');
    Route::post('/promotion/invite/card/make', 'CardController@promotionInviteMake')->middleware('h5.log')->name('cardPromotionInviteMake');
});

Route::group([
    'namespace' => 'MemberCard',
    'prefix'    => 'member',
], function (){
    Route::get('/card/lists','MemberCardController@cardLists')->name('cardLists');
    Route::get('/card/detail','MemberCardController@cardDetail')->name('cardDetail');
});

//推广员管理
Route::group([
    'namespace' => 'Promoters',
    'middleware'=> 'h5.promotion.shop',
    'prefix'    => 'promoters',
], function () {
    //获取基本信息
    Route::get('/info', 'PromotersController@getBasicInfo')->name('basicInfo');
    //申请推广员验证手机号
    Route::get('/check/mobile', 'PromotersController@checkMobile')->name('checkMobile');
    //申请推广员
    Route::post('/apply/promoter', 'PromotersController@applyPromoter')->middleware('h5.log')->name('applyPromoter');
    //推广员列表
    Route::get('/list', 'PromotersController@getPromotersList')->name('promotersList');
    //邀请推广员列表
    Route::get('/invite/list', 'PromotersController@inviteList')->name('promotersInviteList');
    //推广员累积的客户列表
    Route::get('/customer/list', 'PromotersController@getCustomer')->name('customerList');
    Route::get('/member/list', 'PromotersController@memberList')->name('promoterMemberList');
    //推广商品列表
    Route::get('/content/list', 'PromotersController@promotersContentList')->name('promotersContentList');
    //推广订单列表
    Route::get('/order/list', 'PromotersController@promotersOrderList')->name('promotersOrderList');
    Route::get('/record/list', 'PromotersController@recordList')->name('promotersRecordList');
    //推广员收益记录
    Route::get('/profit', 'PromotersController@promotersProfit')->name('promotersProfit');
    //提现记录
    Route::get('/withdraw/money/record', 'PromotersController@withDrawMoneyRecord')->name('withDrawMoneyRecord');
    //统计
    Route::get('/statistics', 'PromotersController@statistics')->name('promotersStatistics');
    //可提现金额
    Route::get('/account/finances', 'PromotersController@withdrawMoney')->name('withdrawMoney');
    //提交提现工单
    Route::post('/withdraw', 'PromotersController@withdraw')->middleware('shop.verify','h5.log')->name('withdraw');
    //获取提现记录
    Route::get('/withdraw/record', 'PromotersController@getWithdrawRecord')->name('getWithdrawRecord');
    //获取提现记录详情
    Route::get('/withdraw/record/{id}', 'PromotersController@getWithdrawRecordDetail')->name('getWithdrawRecordDetail');
    //会员绑定推广员
    Route::post('/member/bind', 'PromotersController@memberBindPromoter')->name('memberBindPromoter');
});


Route::group([
    'namespace' => 'Community',
    'prefix'    => 'community',
], function (){
    //社群列表
    Route::get('/lists','CommunityController@communityLists')->name('communityLists');
    //社群详情
    Route::get('/{id}','CommunityController@communityDetail')->name('communityDetail')->where(['id'=>'\w{12}']);
    //获取社群设置信息
    Route::get('/settings','CommunityController@communitySettings')->name('communitySettings');
    //修改社群设置信息
    Route::post('/settings','CommunityController@setCommunitySettings')->middleware(['check.join.community','h5.log'])->name('setCommunitySettings');
    //社群成员列表
    Route::get('/user/lists','CommunityController@communityUser')->name('communityUser');
    //社群公告列表
    Route::get('/notice/lists','CommunityController@communityNotice')->name('communityNotice');
    //我的社群列表
    Route::get('/user','CommunityController@myCommunityList')->name('myCommunityList');
    //我的收藏列表
    Route::get('/user/collection','CommunityController@myCollectionList')->name('myCollectionList');
    //免费社群加入社群
    Route::get('/join','CommunityController@joinCommunity')->middleware('h5.log')->name('joinCommunity');
    //成员禁言
    Route::get('/user/gag','CommunityController@memberGag')->middleware('check.join.community','check.community.manage','h5.log')->name('communityMemberGag');

    Route::group([
        'prefix'    => 'note',
    ], function (){
        //帖子列表
        Route::get('/lists','CommunityNoteController@noteLists')->name('communityNoteLists');

       /***************需要加入社群才能访问***************/
        //帖子详情
        Route::get('/{id}','CommunityNoteController@noteDetail')->middleware(['check.join.community'])->name('communityNoteDetail')->where(['id'=>'\w{12}']);
        //帖子收藏
        Route::get('/collection','CommunityNoteController@noteCollection')->middleware(['check.join.community','h5.log:id'])->name('noteCollection');
        //帖子点赞
        Route::get('/praise','CommunityNoteController@notePraise')->middleware(['check.join.community','h5.log:id'])->name('notePraise');
        /***************需要加入社群才能访问***************/


        /***************需要管理员才能操作**************/
        //发帖
        Route::post('/post','CommunityNoteController@postNote')->middleware(['check.join.community','check.community.manage','h5.log:id'])->name('communityNotePost');
        //禁言
        Route::get('/gag','CommunityNoteController@noteGag')->middleware(['check.join.community','check.community.manage','h5.log:id'])->name('communityNoteGag');
        //置顶
        Route::get('/top','CommunityNoteController@noteTop')->middleware(['check.join.community','check.community.manage','h5.log:id'])->name('communityNoteTop');
        //设为精华
        Route::get('/boutique','CommunityNoteController@noteBoutique')->middleware(['check.join.community','check.community.manage','h5.log:id'])->name('communityNoteBoutique');
        //删除帖子
        Route::get('/delete','CommunityNoteController@noteDelete')->middleware(['check.join.community','check.community.manage','h5.log:id'])->name('communityNoteDelete');

        /***************需要管理员才能操作**************/

    });
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
    'namespace' => 'LimitPurchase',
    'prefix'    => 'limit'
],function (){
    Route::get('/purchase/lists','LimitPurchaseController@lists')->name('purchaseLists');
    Route::get('/purchase/detail/{id}','LimitPurchaseController@detail')->name('purchaseDetail');
    Route::get('/purchase/contents', 'LimitPurchaseController@contents')->name('purchaseContents');

});

// 日志
Route::group([
    'namespace'  => 'Log',
    'prefix'     => 'log',
],function () {
    Route::post('/weapp/pay/error','WeappController@payError')->name('weappPayError');
});
