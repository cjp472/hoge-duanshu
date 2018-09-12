<?php
define('MEMBER_SECRET',env('MEMBER_SECRET'));
define('PLATFORM',env('PLATFORM','duanshu'));
define('ORDER_CENTER_DOMAIN',env('ORDER_CENTER_DOMAIN'));
define('OAUTH_NO_LOGIN',env('OAUTH_NO_LOGIN'));
define('IMAGE_HOST',env('IMAGE_HOST'));
define('VIDEO_IMAGE_HOST',env('VIDEO_IMAGE_HOST'));
define('QUEUE_NAME',env('QUEUE_NAME'));
define('DEFAULT_QUEUE',env('DEFAULT_QUEUE'));
define('DINGDONE_DOMAIN',env('DINGDONE_DOMAIN'));
define('DUANSHU_DINGDONE_DOMAIN',env('DINGDONE_DUANSHU_DOMAIN'));//短书叮铛直播域名
define('DEFAULT_PASSWORD',env('DEFAULT_PASSWORD',123456));  //默认密码设置
define('OPENPLATFORM_DOMAIN', 'https://api.weixin.qq.com');
define('OPENPLATFORM_WXAPPLET', 'https://api.weixin.qq.com');
define('H5_DOMAIN',env('H5_DOMAIN','http://h5.duanshu.com'));
define('API_DOMAIN',env('API_DOMAIN','http://api.duanshu.com'));
define('PAY_DOMAIN',env('PAY_DOMAIN','http://pay.hogecloud.com'));
define('IS_DOMAIN', 1);
define('HOME_WINDOW_EXPIRE',24*60); //首页弹窗过期时间，分钟
define('LIVE_LECTURE_STATUS_TIME',5*60);    //讲师在线判断时间设置
define('LIVE_INPUT_STATUS_TIME',43200);  //讲师输入状态过期时间设置 12小时
define('MOBILE_CODE_EXPIRE',60*5);
define('EXPIRE_HOUR',30*60);         //过期时间  15分钟
define('EXPIRE_DAY',60*60);        //1小时
define('EXPIRE_MONTH',24*60*60);     //1天
define('USER_ACTIVE',1);
define('MAX_ORDER_PRICE',50000);
define('MEMBER_EXPIRE',3600*30);
define('PRIVATE_MEMBER_EXPIRE',3600);
define('EMAILCODE_EXPIRE',30);
define('DEFAULT_NICK_NAME','空昵称');
define('COMPONENT_LOGIN_PAGE', 'https://mp.weixin.qq.com/cgi-bin/componentloginpage');
define('SUBSCRIBE_MSG', 'https://mp.weixin.qq.com/mp/subscribemsg');
define('PRODUCT_STANDARD_IDENTIFY',env('PRODUCT_STANDARD_IDENTIFY'));    //服务商城标准版商品标识
define('PRODUCT_ADVANCED_IDENTIFY',env('PRODUCT_ADVANCED_IDENTIFY'));    //服务商城高级版商品标识

define('SYNC_COUNT',10);  //内容同步每次传入队列数量
define('ARTICLE_NUMBER', 20); //公众号文章获取数量

define('DEFAULT_STORAGE', 20971520); //默认存储空间（单位kb）
define('DEFAULT_FLOW', 10485760); //默认流量（单位kb）
define('DEFAULT_BASE_STORAGE', 2097152); //默认存储空间（单位kb）
define('DEFAULT_BASE_FLOW', 1048576); //默认流量（单位kb）
define('DEFAULT_PLAT_STORAGE', 83886080); //默认存储空间（单位kb）
define('DEFAULT_PLAT_FLOW', 41943040); //默认流量（单位kb）
define('DEFAULT_STORAGE_UNIT_PRICE', 2); //默认存储单价（单位分 短书币/GB/每天 ）
define('DEFAULT_FLOW_UNIT_PRICE', 90); //默认流量单价（单位分 短书币/GB）

define('DEFAULT_QCLOUD_COS_UNIT_PRICE', 2); //默认存储单价（单位分 短书币/GB/每天 ）
define('DEFAULT_QCLOUD_CDN_UNIT_PRICE', 90); //默认流量单价（单位分 短书币/GB）
define('QCOUND_COS', 'qcloud_cos');     //存储空间
define('QCOUND_CDN', 'qcloud_cdn');     //流量
define('QCOUND_COS_NAME', '存储空间');
define('QCOUND_CDN_NAME', '流量');

define('VERSION_BASIC', 'basic');           //基础版
define('VERSION_STANDARD', 'standard');     //标准版
define('VERSION_ADVANCED', 'advanced');     //高级版
define('VERSION_PARTNER', 'partner');       //合伙人版

define('FUNDS_INCOME', 'income');     //收入
define('FUNDS_EXPAND', 'expand');     //支出

//店铺禁用原因
define('SHOP_DISABLE_BASIC_EXPIRE', 'basic_expire');                    //基础版过期
define('SHOP_DISABLE_BASIC_TEST_EXPIRE', 'test_expire');                //基础版试用过期
define('SHOP_DISABLE_STANDARD_EXPIRE', 'standard_expire');              //标准版过期
define('SHOP_DISABLE_ADVANCED_EXPIRE', 'advanced_expire');              //高级版过期
define('SHOP_DISABLE_TEST_ADVANCED_EXPIRE', 'test_advanced_expire');    //高级版试用过期
define('SHOP_DISABLE_PARTNER_EXPIRE', 'partner_expire');                //全平台版过期
define('SHOP_DISABLE_FUNDS_ARREARS', 'funds_arrears');                  //短书币欠费

define('TIME_NOW', 1530352800);

define('QINIU_NOTIFY_URL', env('QINIU_NOTIFY_URL'));


define('WEAPP_SUPPORT_LOWEST_VERSION', '1.9.0');                        //小程序最低代码版本

//模块状态
define('MODULE_STATUS_CLOSE', 0);
define('MODULE_STATUS_INIT', 1);
define('MODULE_STATUS_OPEN', 2);

return [
    'content_type' => [
        'column'    => '专栏',
        'article'   => '图文',
        'audio'     => '音频',
        'video'     => '视频',
        'live'      => '直播',
        'member_card' => '会员卡',
        'course' => '课程'
    ],
    'sex'   => [
        0 => '未知',
        1 => '男',
        2 => '女'
    ],
    'pay' => [
        'plat'      => [
            'mch_id'        => env('PAY_MCH_ID'),
            'access_key'    => env('PAY_ACCESS_KEY'),
            'access_secret' => env('PAY_ACCESS_SECRET'),
            'return_url'    => H5_DOMAIN,
            'notify_url'    => API_DOMAIN.'/order/callback',
            'admire_url'    => API_DOMAIN.'/admire/callback',
            'register_mch'    => PAY_DOMAIN.'/service_api/pay/merchants/register/',
        ],
        'youzan'    => [
            'app_id'         => env('YOUZAN_APPID'),
            'app_secret'     => env('YOUZAN_APPSECRET'),
        ],
        'applet' => [
            'notify_url'    => API_DOMAIN.'/applet/order/callback',
            'admire_url'    => API_DOMAIN.'/applet/admire/callback'
        ],
    ],
    'h5url' => H5_DOMAIN.'/{shop_id}/#/giftcode/{code}',
    'permission'    => [
        'content'       => '内容管理',
        'column'        => '我的专栏',
        'comment'       => '内容评论',
        'banner'        => '轮播图',
        'member'        => '我的会员',
        'message'        => '会员消息',
        'invitation'    => '邀请码',
        'income_analysis'   => '收入分析',
        'content_analysis'  => '内容分析',
        'member_analysis'   => '会员分析',
        'roleManage'          => '角色管理',
        'flow'           => '流量管理',
        'interface'     => '手机预览',
        'order'         => '订单流水',
        'material'      => '素材管理',
        'navigation'    => '导航分类',
        'open_platform' => '小程序',
        'admire'    => '赞赏',
        'color'     => '配色',
        'black'     => '黑名单',
        'score'     => '短书币',
        'applet'   => '小程序',
        'course'    => '课程',
        'private'   => '私密账号',
        'promotion'   => '推广员',
        'community' => '小社群',
        'sdk' => 'sdk',
        'limit'  => '限时购',
        'protocol' => '订购协议',
        'obs'         => 'obs直播',
        'info'  => '店铺信息设置',
    ],
    'basic'    => [
        'content', 'comment', 'column', 'member', 'banner', 'message' , 'invitation', 'code_share', 'income_analysis', 'content_analysis', 'member_analysis', 'interface', 'order', 'roleManage', 'flow', 'applet', 'class','score','order_analysis','shop',
    ],
    'standard'    => [
        'content', 'comment', 'column', 'banner', 'member', 'message', 'invitation', 'code_share', 'income_analysis', 'content_analysis', 'member_analysis', 'roleManage', 'flow', 'interface', 'order', 'material', 'navigation', 'shop_update', 'open_platform', 'admire', 'color', 'course', 'applet', 'private', 'promotion', 'community', 'class', 'xiuzan', 'obs', 'protocol', 'xiuzan', 'score','limit','info','remind','order_analysis','shop',
    ],
    'advanced'    => [
        'content', 'comment', 'column', 'banner', 'member', 'message', 'invitation', 'code_share', 'income_analysis', 'content_analysis', 'member_analysis', 'roleManage', 'flow', 'interface', 'order', 'material', 'navigation', 'shop_update', 'open_platform', 'admire', 'color', 'course', 'applet', 'private', 'promotion', 'community', 'class', 'xiuzan', 'obs', 'protocol', 'xiuzan', 'score','limit','info','remind','order_analysis','shop',
    ],
    'partner'    => [
        'content', 'comment', 'column', 'banner', 'member', 'message', 'invitation', 'code_share', 'income_analysis', 'content_analysis', 'member_analysis', 'roleManage', 'flow', 'interface', 'order', 'material', 'navigation', 'shop_update', 'open_platform', 'admire', 'color', 'course', 'applet', 'private', 'promotion', 'community', 'class', 'xiuzan', 'obs', 'protocol', 'xiuzan', 'score','sdk','limit','info','remind','order_analysis','shop',
    ],
    'unactive-partner'    => [
        'content', 'comment', 'column', 'banner', 'member', 'message', 'invitation', 'code_share', 'income_analysis', 'content_analysis', 'member_analysis', 'roleManage', 'flow', 'interface', 'order', 'material', 'navigation', 'shop_update', 'open_platform', 'admire', 'color', 'course', 'applet', 'private', 'promotion', 'community', 'class', 'xiuzan', 'obs', 'protocol', 'xiuzan', 'score','sdk','limit','info','remind','order_analysis','shop',
    ],
    'order_center' => [
        'app_id'         => env('ORDER_CENTER_APPID'),
        'app_secret'     => env('ORDER_CENTER_APPSECRET'),
        'api'   => [
            'order_create'    => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/',
            'order_list'    => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/',
            'order_detail'  => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/{order_no}/',
            'order_status'  => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/{order_no}/status/',
            'order_blocked' => ORDER_CENTER_DOMAIN.'/admin_api/memberorder/order/',
            'order_total'  => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/user/total/',
            'order_overview'  => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/overview/',
            'order_incomes'  => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/user/incomes/',
            'withdraw_money' => ORDER_CENTER_DOMAIN.'/service_api/withdraw/account/finances/',//提现金额
            'verify_detail'  => ORDER_CENTER_DOMAIN.'/service_api/verify/detail/',
            'order_undeliver'  => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/{order_no}/undeliver/',
            'm_withdraw_money' => ORDER_CENTER_DOMAIN.'/service_api/m-withdraw/account/finances/',//会员提现金额
            'm_withdraw_workorders' => ORDER_CENTER_DOMAIN.'/service_api/m-withdraw/workorders/',//提交提现工单
            'm_withdraw_record' => ORDER_CENTER_DOMAIN.'/service_api/m-withdraw/workorders/',//会员提现工单记录查询
            'm_withdraw_record_detail' => ORDER_CENTER_DOMAIN.'/service_api/m-withdraw/workorders/{id}',//会员提现工单记录详情

            'm_order_refunds' => ORDER_CENTER_DOMAIN.'/service_api/memberorder/refund/',//会员退款申请
            'order_close'  => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/{order_no}/closed/',   //订单关闭
            'm_withdraw_money_combine' => ORDER_CENTER_DOMAIN.'/service_api/m-withdraw/account/combine/finances/',//多账户会员提现金额
            'm_withdraw_workorders_combine' => ORDER_CENTER_DOMAIN.'/service_api/m-withdraw/workorders/combine/create/',//多账户提交提现工单
            'm_withdraw_record_combine' => ORDER_CENTER_DOMAIN.'/service_api/m-withdraw/workorders/combine/',//多账户会员提现工单记录查询
            'm_order_refunds_pass' => ORDER_CENTER_DOMAIN.'/service_api/memberorder/refund/{refund_id}/status/pass/',//卖家通过退款申请
            'm_order_summary_search' => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/summary/search/',//查询业务方订单查询汇总数据
            'm_order_confirm' => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/{order_no}/confirm/',//更新订单为成功
            'm_order_refunds_success' => ORDER_CENTER_DOMAIN.'/service_api/memberorder/refund/{refund_id}/status/success/',//卖家通过退款申请
            'm_order_update' => ORDER_CENTER_DOMAIN.'/service_api/memberorder/order/{order_no}/',//修改订单中心订单
            'm_order_refunds_detail' => ORDER_CENTER_DOMAIN.'/service_api/memberorder/refund/{id}/',//退款申请详情

            
        ],
    ],
    'service_store'=>[
        'sign'  => [
            'key'    => env('ORDER_CENTER_SIGNKEY'),
            'secret' => env('ORDER_CENTER_SIGNSECRET'),
        ],
        'api' =>[
            'goods_list'   => ORDER_CENTER_DOMAIN.'/service_api/products/',
            'order_list'   => ORDER_CENTER_DOMAIN.'/service_api/orders/',
            'shop_score'   => ORDER_CENTER_DOMAIN.'/service_api/usertoken/profile/',
            'score_manage'   => ORDER_CENTER_DOMAIN.'/service_api/usertoken/manage/',
            'storage'   => ORDER_CENTER_DOMAIN.'/service_api/cloudbillings/cos/',
            'cloudbilling_consume'   => ORDER_CENTER_DOMAIN.'/service_api/cloudbillings/consume/',
            'sms' => ORDER_CENTER_DOMAIN.'/service_api/notify_service/sms/'
        ],
        'app_id'       => env('ORDER_CENTER_APPID'),
        'app_secret'    => env('ORDER_CENTER_APPSECRET'),
    ],
    'year_time' =>[
        '三个月'  => 3,
        '半年'  => 6,
        '一年'  => 12,
        '两年'  => 24,
        '三年'    => 36,
    ],

    'admin_signature_id' => explode(',',env('ADMIN_SIGNATURE_ID')), //添加后台授权登录id
    'admin_super_id'    => explode(',',env('ADMIN_SUPER_ID')),       //短书运营后台超级管理者
    'admin_permission_except'  => [   //普通管理者没有的权限
        'shop_update',
//        'black'
    ],

    'open_platform' => [
        'wx_applet' => [
            'api' => [
                'api_component_token' => OPENPLATFORM_DOMAIN . '/cgi-bin/component/api_component_token',
                'api_authorizer_token' => OPENPLATFORM_DOMAIN . '/cgi-bin/component/api_authorizer_token',
                'api_create_preauthcode' => OPENPLATFORM_DOMAIN . '/cgi-bin/component/api_create_preauthcode',
                'api_query_auth' => OPENPLATFORM_DOMAIN . '/cgi-bin/component/api_query_auth',
                'api_get_authorizer_info' => OPENPLATFORM_DOMAIN . '/cgi-bin/component/api_get_authorizer_info',
                'jscode2session' => OPENPLATFORM_DOMAIN . '/sns/component/jscode2session',

                'modify_domain' => OPENPLATFORM_DOMAIN . '/wxa/modify_domain',
                'web_view_domain' => OPENPLATFORM_DOMAIN . '/wxa/setwebviewdomain',
                'bind_tester' => OPENPLATFORM_DOMAIN . '/wxa/bind_tester',
                'unbind_tester' => OPENPLATFORM_DOMAIN . '/wxa/unbind_tester',
                'commit' => OPENPLATFORM_DOMAIN . '/wxa/commit',
                'get_qrcode' => OPENPLATFORM_DOMAIN . '/wxa/get_qrcode',
                'get_category' => OPENPLATFORM_DOMAIN . '/wxa/get_category',
                'get_page' => OPENPLATFORM_DOMAIN . '/wxa/get_page',
                'submit_audit' => OPENPLATFORM_DOMAIN . '/wxa/submit_audit',
                'get_auditstatus' => OPENPLATFORM_DOMAIN . '/wxa/get_auditstatus',
                'get_latest_auditstatus' => OPENPLATFORM_DOMAIN . '/wxa/get_latest_auditstatus',
                'release' => OPENPLATFORM_DOMAIN . '/wxa/release',
                'change_visitstatus' => OPENPLATFORM_DOMAIN . '/wxa/change_visitstatus',
                'getwxacodeunlimit' => OPENPLATFORM_DOMAIN . '/wxa/getwxacodeunlimit',
                'getwxacode' => OPENPLATFORM_DOMAIN . '/wxa/getwxacode',
                'getweappsupportversion' => OPENPLATFORM_DOMAIN . '/cgi-bin/wxopen/getweappsupportversion',
                'setweappsupportversion' => OPENPLATFORM_DOMAIN . '/cgi-bin/wxopen/setweappsupportversion',
            ],
            'validation' => [
                //通用
                '-1'    => '系统繁忙',
                //修改服务器域名
                '85017' => '请勿重复添加服务器域名',
                //绑定微信用户为小程序体验者
                '85001' => '微信号不存在或微信号设置为不可搜索',
                '85002' => '小程序绑定的体验者数量达到上限',
                '85003' => '微信号绑定的小程序体验者达到上限',
                '85004' => '微信号已经绑定',
                //为授权的小程序帐号上传小程序代码
                '85013' => '无效的自定义配置',
                '85014' => '无效的模版编号',
                //获取小程序的第三方提交代码的页面配置
                //将第三方提交的代码包提交审核
                '86000' => '不是由第三方代小程序进行调用',
                '86001' => '不存在第三方的已经提交的代码',
                '85006' => '标签格式错误',
                '85007' => '页面路径错误',
                '85008' => '类目填写错误',
                '85009' => '系统检测到您在其他平台提交了审核版本，请审核完成后再提交审核',
                '85010' => 'item_list有项目为空',
                '85011' => '标题填写错误',
                '85023' => '审核列表填写的项目数不在1-5以内',
                '86002' => '小程序还未设置昵称、头像、简介。请先设置完后再重新提交',
                //发布已通过审核的小程序
                '85019' => '没有审核版本',
                '85020' => '审核状态未满足发布',
                '85052' => '该小程序已经发布',
                //修改小程序线上代码的可见状态
                '85021' => '状态不可变',
                '85022' => 'action非法',
                '61007' => '微信授权不完整，请授权时勾选所有管理权限',
                '48001' => '微信授权不完整，请授权时勾选素材管理权限',
                '61023' => '无效的刷新token',
                '45009' => '今日导入文章数量已达上限，已导入300篇，请明天再试',
            ],
            'func_info' => [
                17 => '帐号管理权限',
                18 => '开发管理与数据分析权限',
                19 => '客服消息管理权限',
                25 => '微信开放平台账号绑定权限',
            ],
        ],
        'public' => [
            'api' => [
                'batchget_material' => OPENPLATFORM_DOMAIN . '/cgi-bin/material/batchget_material',
                'get_material' => OPENPLATFORM_DOMAIN . '/cgi-bin/material/get_material',
                'get_materialcount' => OPENPLATFORM_DOMAIN . '/cgi-bin/material/get_materialcount',

            ],
        ],
        'requestdomain' => [
            'https://api.duanshu.com',
            'https://appletapi.duanshu.com'
        ],
        'wsrequestdomain' => [],
        'uploaddomain' => [
            'https://api.duanshu.com',
            'https://appletapi.duanshu.com',
            'https://sh.file.myqcloud.com',
        ],
        'downloaddomain'    => [
            'https://duanshu-1253562005.picsh.myqcloud.com',
            'https://duanshu-1253562005.cossh.myqcloud.com',
            'https://pimg.duanshu.com',
            'https://upload.duanshu.com',
            'https://dianbo.duanshu.com'
        ],
        'webviewdomain' => [
            'https://member.xiuzan.com',
            'https://public.xiuzan.com',
            'https://result.xiuzan.com',
            'https://form.xiuzan.com',
            #'https://pimg.xiuzan.com',
            #'https://h5.xiuzan001.cn',
            'https://h5.xiuzan.com',
        ],
    ],

    //短书内部配置
    'inner_config' => [
        'sign'  => [
            'key'   => env('INNERCONFIG_SIGN_KEY'),
            'secret'    => env('INNERCONFIG_SIGN_SECRET'),
        ],
        'api'   => [
            'getH5QRcode' => API_DOMAIN.'/qrcode/make',
            'refund_order_retry'=> API_DOMAIN.'/server/fight/refund/retry',
            'fight_group_retry'=> API_DOMAIN.'/server/fight/retry'
        ],

    ],
    'default_avatar' => IMAGE_HOST.'/dsapply/image/1503385234634_442669.jpg',
    'default_card' => IMAGE_HOST.'/dsapply/image/1515735531920_334819.jpg', //会员卡默认分享图片

    //短书同步数据到叮铛配置
    'dingdone'  => [
        'key'       => env('DINGDONE_KEY'),
        'secret'    => env('DINGDONE_SECRET'),
        'api'       => [
            'bulk_create_or_update' => DINGDONE_DOMAIN.'/open/{app_id}/contents/bulk_create_or_update/',
            'singleContent'         => DINGDONE_DOMAIN.'/open/{app_id}/contents/create_or_update/',
            'member'                => DINGDONE_DOMAIN.'/open/{app_id}/members/sync/',
            'add_user_to_group'     => DINGDONE_DOMAIN.'/open/{app_id}/members/add_user_to_group/',
            'comments'              => DINGDONE_DOMAIN.'/open/{app_id}/comments/bulk_create/',
            'commentsDelete'        => DINGDONE_DOMAIN.'/open/{app_id}/comments/{ori_id}/',
            'contentsDelete'         => DINGDONE_DOMAIN.'/open/{app_id}/contents/bulk_delete/',
            'commentAdd'            => DINGDONE_DOMAIN.'/open/{app_id}/comments/bulk_create/',
            'orderList'             => DINGDONE_DOMAIN.'/open/{app_id}/business/app_orders/',
        ],
    ],

    //短书APP数据同步内部路由配置
    'batch_sync_content' => [
        'column'       => API_DOMAIN.'/app/sync/column',
        'article'      => API_DOMAIN.'/app/sync/article',
        'video'        => API_DOMAIN.'/app/sync/video',
        'audio'        => API_DOMAIN.'/app/sync/audio',
        'live'         => API_DOMAIN.'/app/sync/live',
        'member'       => API_DOMAIN.'/app/sync/member',
        'order'        => API_DOMAIN.'/app/sync/order',
        'comment'      => API_DOMAIN.'/app/sync/comment',
        'type'         => API_DOMAIN.'/app/sync/type',
        'banner'       => API_DOMAIN.'/app/sync/banner',
    ],
    //同步数据到APP时，付费类型转换
    'payment_type'  => [
        1     => '专栏订阅',
        2       => '单卖',
        3       => '免费',
        4       => '专栏外单卖',
    ],

    'M2O'   => [
        'param' => [
            'state_prefix' => 'm2oapp',
            'client_id'     => env('M20_CLIENT_ID'),
            'client_secret' => env('M2O_CLIENT_KEY'),
        ],
        'api'   => [
            'token' => env('M2O_DOMAIN').'/oauth/token',
            'user'  => env('M2O_DOMAIN').'/api/user',
            'obs_info'  => 'http://starshow.cloud.hoge.cn/anchorapi/ds_url/',
            'member_register'  => 'http://mobile.v1.m2odemo.com/xys/m_register.php',
            'member_login'  => 'http://starshow.cloud.hoge.cn/anchorapi/3988c7f88ebcb58c6ce932b957b6f332/m2o_login/'
        ],
        'redirect'  => env('M2O_DUANSHU_REDIRECT'),

        'member'    => [
            'appid' => env('M2O_MEMBER_APPID',46),
            'appkey' => env('M2O_MEMBER_APPKEY','odhyT916hMRgZV4W7F1LF6vmyhF5fGSt'),
        ]
    ],


    'applet_test_shop'   => explode(',',env('APPLET_TEST_SHOP')),

    'dingding'  => [
        'notice'       => [
            'api'   => 'https://oapi.dingtalk.com/robot/send',
            'token' => env('DING_TOKEN'),
            'link'  => env('DING_LINK'),
        ],
    ],

    //秀赞接入配置
    //短书python项目配置
    'python_duanshu' => [
        'api'   => [
            'group_create'  => API_DOMAIN.'/fairy/api/open/v1/fightgroup/initiate/',
            'group_join'  => API_DOMAIN.'/fairy/api/open/v1/fightgroup/join/',
            'group_join_check'  => API_DOMAIN.'/fairy/api/open/v1/fightgroup/join/check/',
            'group_create_check'  => API_DOMAIN.'/fairy/api/open/v1/fightgroup/initiate/check/',
            'ceate_live_chat_group' => API_DOMAIN. '/fairy/open/v1/lives/%s/chatgroup/',// {live_id} 创建删除直播聊天组
            'add_member_to_chat_group' => API_DOMAIN . '/fairy/open/v1/lives/%s/chatgroup/add_members/', //增加直播聊天组成员
            'send_chat_group_admire_msg' => API_DOMAIN . '/fairy/open/v1/lives/%s/chatgroup/send_admire_msg/', //发送赞赏消息
            'delete_member_to_chat_group' => API_DOMAIN . '/fairy/open/v1/lives/%s/chatgroup/',// 删除直播
            'internal_register'  => API_DOMAIN.'/fairy/api/open/v1/callbacks/internal/register/',
            'internal_order'  => API_DOMAIN.'/fairy/api/open/v1/callbacks/internal/order/',
            'internal_version_update'  => API_DOMAIN.'/fairy/api/open/v1/callbacks/internal/version/update/',
        ]
    ],

    'xiuzan'    => [
        'param' => [
            'client_id'    => env('XIUZAN_CLIENT_ID'),
            'client_secret'=> env('XIUZAN_CLIENT_KEY'),
        ],
        'api'   => [
            'lists'     => env('XIUZAN_DOMAIN').'/open/archive/lists',
            'xiuzan'    => env('XIUZAN_DOMAIN').'/duanshu?',
        ],
    ],

    //短书客户端配置
    'duanshu_client'    => [
        'client_secret' => env('DUANSHU_CLIENT_SECRET','duanshu'),

    ],

    //订单状态映射
    'order_status_map' => [
        'success'   => 1,
        'closed'    => -1,
        'unpaid'    => 0,
        'paying'    => 0,
        'confirming'=> -6
    ],

    'test_mobile' => [
        '15555555555',
    ],

    'host_exist' => [
        'admin', 'h5', 'api', 'store', 'help', 'school', 'www', 'my',
    ],
];