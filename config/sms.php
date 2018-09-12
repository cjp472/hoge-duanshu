<?php
/**
 * 短信相关配置
 */
return [
    'account'   => 'dh21453',
    'password'  => 'tp19x73z',
    'sign'      => '【短书】',
    'subcode'   => '001',
    'api'       => [
        'submit'        => 'http://wt.3tong.net/json/sms/Submit',
        'batch_submit'  => 'http://wt.3tong.net/json/sms/BatchSubmit',

        //内部路由
        'mobile_send'   => 'http://tech_api.hogecloud_new.com/sms/submit',
        'aliyun_send'   => 'http://tech_api.hogecloud_new.com/ali/sms/send'

    ],
    'template' => [
        'mobile_code'   => '验证码:{code},五分钟内有效,为保证您的账户安全,请勿向他人泄露验证码信息.'
    ],
    'sign_param' => [
        'key'    => 'duanshu',
        'secret' => 'duanshu',
    ],

    'aliyun' => [
        'accessKeyId'   => env('ALIMSG_ACCESS_KEY_ID','J2Fb3P6H9zLjF7ve'),
        'accessKeySecret'   => env('ALIMSG_ACCESS_KEY_SECRET','go0gx60Y1ZQPvvFjcwoc8u8CATQCB5'),
        'product'   => 'Dysmsapi',      //短信API产品名（短信产品名固定，无需修改）
        'domain'    => 'dysmsapi.aliyuncs.com',     //短信API产品域名（接口地址固定，无需修改）
        'region'    => 'cn-hangzhou',               //暂时不支持多Region（目前仅支持cn-hangzhou请勿修改）
        'signName'  => '短书',                    //签名
        'templateCode'  => [
            'verify'    => [//验证码
                'template_id' => 'SMS_78410033',
                'param'       => [
                    'code'  => '',
                ],
            ],
            'notice'    => [ //短信通知
                'template_id' => 'SMS_78410033',
                'param'       => [

                ],
            ]
        ],
    ]
];