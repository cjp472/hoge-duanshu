<?php

return [

    'driver' => env('MAIL_DRIVER', 'smtp'),

    'host' => env('MAIL_HOST', 'smtpdm.aliyun.com'),

    'port' => env('MAIL_PORT', 25),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'service@mail.xiuzan.com'),
        'name' => env('MAIL_FROM_NAME', '短书'),
    ],

    'encryption' => env('MAIL_ENCRYPTION', 'tls'),

    'username' => env('MAIL_USERNAME','service@mail.xiuzan.com'),

    'password' => env('MAIL_PASSWORD','XIUzan1234'),

    'sendmail' => '/usr/sbin/sendmail -bs',


    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

    'aliemail'   => [
        'regionId'        => 'cn-hangzhou',
        'accessKeyId'     => 'V4R9G6dyoevMMbNp',
        'accessSecret'    => 'gDiWBu2tIiVjAf20zeZxvdIUrYANTy',
        'AccountName'     => 'service@mail.xiuzan.com',
        'FromAlias'       => '秀赞',
        'TagName'         => 'feedback',
    ],

];
