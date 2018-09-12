<?php
return [
    'appid'         => env('QCLOUD_APPID'),
    'secret_id'     => env('QCLOUD_SECRET_ID'),
    'secret_key'    => env('GCLOUD_SECRET_KEY'),

    'cos'           => [
        'bucket'    => env('QCLOUD_BUCKET'),
        'signature_expire_time' => 7200,
    ],

    'region'        => env('QCLOUD_REGION'),

    'folder'        => env('QCLOUD_FOLDER'),

    'classId'       => env('QCLOUD_CLASS_ID'),
    'delete'        =>[
        'host'      =>  'vod.api.qcloud.com',
        'path'      =>  '/v2/index.php'
    ],

    'vod' => [
        'host'      =>  'vod.api.qcloud.com',
        'path'      =>  '/v2/index.php',
        'original_definition' => 0,
        'hls_sd_definition'=> 220,
        'hls_hd_definition'=> 230,
        'custom_hd_definition'=> 19341,
    ]
];
