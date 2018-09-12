<?php
/**
 * Created by PhpStorm.
 * User: tanqiang
 * Date: 2018/9/4
 * Time: 下午12:26
 */

return array(
    'disks' => [
        'qiniu' => [
            'driver' => 'qiniu',
            'domains' => [
                'default' => 'pegqnby93.bkt.clouddn.com', //你的七牛域名
                'https' => '',         //你的HTTPS域名
                'custom' => '',     //你的自定义域名
            ],
            'access_key' => 'ryxU0k5QgGCiyfiFi3TJ4Tx3Inug3L-TZAQ5m1Vd',  //AccessKey
            'secret_key' => 'yYr_-lYExn8rFtAF0fh0w6PgSqNm23BDIGGRvgbx',  //SecretKey
            'bucket' => 'duanshu',  //Bucket名字
            'notify_url' => QINIU_NOTIFY_URL,  //持久化处理回调地址
        ],
    ],
);