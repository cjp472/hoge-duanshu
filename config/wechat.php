<?php

return [
    /*
     * Debug 模式，bool 值：true/false
     *
     * 当值为 false 时，所有的日志都不会记录
     */
    'debug'  => false,

    /*
     * 使用 Laravel 的缓存系统
     */
    'use_laravel_cache' => true,

    /*
     * 账号基本信息，请从微信公众平台/开放平台获取
     */
    'app_id'  => env('WECHAT_APPID', 'wxaaf36f23d824fbc4'),         // AppID
    'secret'  => env('WECHAT_SECRET', 'c5bcc9523e717280df0b81227af96beb'),     // AppSecret

    'token'   => env('WECHAT_TOKEN', 'your-token'),          // Token
    'aes_key' => env('WECHAT_AES_KEY', ''),                    // EncodingAESKey

    'api'   => [
        'media_get' => 'http://file.api.weixin.qq.com/cgi-bin/media/get?',
        'jscode2session'    => 'https://api.weixin.qq.com/sns/component/jscode2session',
    ],

    /**
     * 开放平台第三方平台配置信息
     */
    'open_platform' => [
        'app_id'   => env('OPEN_PLATFORM_APPID'),
        'secret'   => env('OPEN_PLATFORM_APPSECRET'),
        'token'    => env('OPEN_PLATFORM_TOKEN'),
        'aes_key'  => env('OPEN_PLATFORM_AES_KEY'),
        'serve_url' => env('WECHAT_OPEN_PLATFORM_SERVE_URL', 'serve'),
    ],

    /**
     * 微信小程序配置信息
     */
    'mini_program' => [
        'app_id'   => env('MINI_PROGRAM_APPID'),//'wx6658cd4959a5908f',
        'secret'   => env('MINI_PROGRAM_APPSECRET'),
        'token'    => env('MINI_PROGRAM_TOKEN'),
        'aes_key'  => env('MINI_PROGRAM_AES_KEY')
    ],

    /**
     * 多个小程序账号登录配置，临时配置的，以后走开放平台
     */
    'mini_accounts' => [
        'wx48ca936c392b3109' => '72ec40d8f9b77cf484234dd12f0dc236',
        'wxaaf36f23d824fbc4'    => '80ebc776c389f503af80e5ba38e77c69',
        'wxdef25adc4ca2664e'    =>   'c3917118c0082233a20f0dc4a027620a',
        'wx44044893e80e0844'    =>   '6dd364478b2b9e04ac55e3b30a30101d',
        'wxfb37c306a5ac654a'    => 'facbf8f347996bf74cd385c161988c9e',
        'wx04d7b3907ac94e04'    => '73614e2807a528f5d4faef5bbd9887d3',
        'wx096f4ff021cdb35f' => '5d91949b64fa9576137318a9b15ee782',
        'wx22031b8a8ddd50c8'    => '1cdfbe11d02aa7f4eacfd3197ac94a2c',
        'wxb57bc0733f75dc9f'    => 'f763e0fa20002e841d6251bd75958915',
        'wx60e5e5e81de9b44f'     => '019084d77e8b758bd9f2db0f8bb193cd',
        'wx3d649da975f30673'    => '2d87aabc8ca23133ad34c141d1cfa2ca',
        'wxd359b8dbfbe9fe48'    => '81189bfe1e9f17b74bb78780c0005c30',
        'wx530ec36356f03e21'    => 'b3ed31c97d23d1ee34dedebd70e473e7',
        'wxea4b738cd23f515c'    => '49aa9c2ed51697fb8ad434edca4d6351',
        'wxbb19cbc84a9a1896'    => '633096b121e5a457916212ab5d50966c'
    ],

    /*
     * 日志配置
     *
     * level: 日志级别，可选为：
     *                 debug/info/notice/warning/error/critical/alert/emergency
     * file：日志文件位置(绝对路径!!!)，要求可写权限
     */
    'log' => [
        'level' => env('WECHAT_LOG_LEVEL', 'debug'),
        'file'  => env('WECHAT_LOG_FILE', storage_path('logs/wechat.log')),
    ],

    /*
     * OAuth 配置
     *
     * only_wechat_browser: 只在微信浏览器跳转
     * scopes：公众平台（snsapi_userinfo / snsapi_base），开放平台：snsapi_login
     * callback：OAuth授权完成后的回调页地址(如果使用中间件，则随便填写。。。)
     */
     'oauth' => [
         'only_wechat_browser' => false,
         'scopes'   => array_map('trim', explode(',', env('WECHAT_OAUTH_SCOPES', 'snsapi_userinfo'))),
         'callback' => env('WECHAT_OAUTH_CALLBACK', '/h5/user/wechat/callback'),
     ],

    /*
     * 微信支付
     */
     'payment' => [
         'merchant_id'        => env('WECHAT_PAYMENT_MERCHANT_ID', '1344445501'),
         'key'                => env('WECHAT_PAYMENT_KEY', '6uFefq04q3Pjbgg1K6Ggzm4t00nEB2Qn'),
         'cert_path'          => env('WECHAT_PAYMENT_CERT_PATH', 'path/to/your/cert.pem'), // XXX: 绝对路径！！！！
         'key_path'           => env('WECHAT_PAYMENT_KEY_PATH', 'path/to/your/key'),      // XXX: 绝对路径！！！！'key_path'           => env('WECHAT_PAYMENT_KEY_PATH', 'path/to/your/key'),      // XXX: 绝对路径！！！！
         'notify_url'           => env('WECHAT_PAYMENT_NOTIFY_URL', 'https://pre-api.duanshu.com/applet/refund/callback'),      // XXX: 绝对路径！！！！'key_path'           => env('WECHAT_PAYMENT_KEY_PATH', 'path/to/your/key'),      // XXX: 绝对路径！！！！
         // 'device_info'     => env('WECHAT_PAYMENT_DEVICE_INFO', ''),
         // 'sub_app_id'      => env('WECHAT_PAYMENT_SUB_APP_ID', ''),
         // 'sub_merchant_id' => env('WECHAT_PAYMENT_SUB_MERCHANT_ID', ''),
         // ...

     ],

    /*
     * 开发模式下的免授权模拟授权用户资料
     *
     * 当 enable_mock 为 true 则会启用模拟微信授权，用于开发时使用，开发完成请删除或者改为 false 即可
     */
    // 'enable_mock' => env('WECHAT_ENABLE_MOCK', true),
    // 'mock_user' => [
    //     "openid" =>"odh7zsgI75iT8FRh0fGlSojc9PWM",
    //     // 以下字段为 scope 为 snsapi_userinfo 时需要
    //     "nickname" => "overtrue",
    //     "sex" =>"1",
    //     "province" =>"北京",
    //     "city" =>"北京",
    //     "country" =>"中国",
    //     "headimgurl" => "http://wx.qlogo.cn/mmopen/C2rEUskXQiblFYMUl9O0G05Q6pKibg7V1WpHX6CIQaic824apriabJw4r6EWxziaSt5BATrlbx1GVzwW2qjUCqtYpDvIJLjKgP1ug/0",
    // ],
];
