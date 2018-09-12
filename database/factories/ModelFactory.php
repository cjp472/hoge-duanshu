<?php

$factory->define(App\Models\User::class, function (Faker\Generator $faker) {
    static $password;
    $email = $faker->unique()->safeEmail;
    return [
        'name' => $faker->name,
        'email' => $email,
        'password' => $password ?: $password = bcrypt('123123'),
        'remember_token' => str_random(10),
        'username' => $email,
        'source'    => 'email',
        'mobile'    => $faker->phoneNumber,
        'created_at' => $faker->dateTimeBetween('-1 year'),
    ];
});

$factory->define(App\Models\Shop::class, function (Faker\Generator $faker) {
    return [
        'hashid' => str_random(18),
        'title' => $faker->company,
        'brief' => $faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company,
        'create_time' => $faker->dateTimeBetween('-1 year')->getTimestamp(),
    ];
});

$factory->define(App\Models\UserShop::class, function () {
    return [
        'user_id' => function () {
            return factory(App\Models\User::class)->create()->id;
        },
        'shop_id' => function () {
            return factory(App\Models\Shop::class)->create()->hashid;
        },
        'permission' => '',
        'effect'    => 1,
        'admin'     => 1,
    ];
});


$factory->define(App\Models\Article::class,function (Faker\Generator $faker) {
    return [
        'content_id' => function () use ($faker) {
            $shop = factory(App\Models\UserShop::class)->make();
            return factory(App\Models\Content::class)->create( [
                'hashid' => str_random(12),
                'shop_id'   => function () use ($shop) {
                    return $shop->shop_id;
                },
                'title'     => $faker->company,
                'brief' => $faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company,
                'type'  => 'article',
                'payment_type'  => 3,
                'price' => 0.00,
                'up_time'   => time(),
                'create_time'   => $faker->dateTimeBetween('-1 month')->getTimestamp(),
                'update_time'   => time(),
                'create_user'   => function () use ($shop) {
                    return $shop->user_id;
                },
                'update_user'  => function () use ($shop) {
                    return $shop->user_id;
                },
                'state' => 1,
            ])->hashid;
        },
        'content'   => $faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company,
    ];
});



$factory->define(App\Models\Audio::class,function (Faker\Generator $faker) {
    return [
        'content_id' => function () use ($faker) {
            $shop = factory(App\Models\UserShop::class)->make();
            return factory(App\Models\Content::class)->create( [
                'hashid' => str_random(12),
                'shop_id'   => function () use ($shop) {
                    return $shop->shop_id;
                },
                'title'     => $faker->company,
                'brief' => $faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company,
                'type'  => 'audio',
                'payment_type'  => 3,
                'price' => 0.00,
                'up_time'   => time(),
                'create_time'   => $faker->dateTimeBetween('-1 month')->getTimestamp(),
                'update_time'   => time(),
                'create_user'   => function () use ($shop) {
                    return $shop->user_id;
                },
                'update_user'  => function () use ($shop) {
                    return $shop->user_id;
                },
                'state' => 1,
            ])->hashid;
        },
        'content'   => $faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company.$faker->address.$faker->company,
        'url'   => $faker->imageUrl,
        'file_name' => $faker->title.'.mp3',
        'size'  => $faker->randomFloat(2,0,100),
    ];
});


$factory->define(App\Models\Content::class,function (Faker\Generator $faker) {
    return [
    ];
});


$factory->define(App\Models\Member::class,function (Faker\Generator $faker) {
    return [
        'uid'   => str_random(32),
        'shop_id'   => function(){
            $ids = factory(App\Models\Shop::class)->make()->select('hashid')->get();
            $shop =$ids->random()->hashid;
            return $shop;
        },
        'openid'    => str_random(28),
        'union_id'  => str_random(28),
        'source'    => 'wechat',
        'avatar'    => $faker->imageUrl(),
        'nick_name' => $faker->name(),
        'true_name' => $faker->name(),
        'sex'   => $faker->numberBetween(0,2),
        'birthday'  => $faker->dateTimeBetween('-30 years')->getTimestamp(),
        'mobile'    => $faker->phoneNumber,
        'email' => $faker->email,
        'create_time'   => $faker->dateTimeBetween('-1 years')->getTimestamp(),
        'amount'    => $faker->randomFloat(2,0,100),
        'address'   => $faker->address,
        'company'   => $faker->company,
        'position'  => $faker->jobTitle,
        'industry'    => $faker->jobTitle,
        'language'  => 'zh_CN',
        'ip'    => $faker->ipv4,
        'province'   => $faker->state,
    ];
});


$factory->define(App\Models\VersionOrder::class,function (Faker\Generator $faker) {
    $ids = factory(App\Models\Shop::class)->make()->select('hashid')->get();
    $shop =$ids->random()->hashid;
    $success_time =  $faker->dateTimeBetween('-3 month')->getTimestamp();
    factory(App\Models\VersionExpire::class)->create([
        'hashid'    => $shop,
        'version'   => 'advanced',
        'start'     => $success_time,
        'expire'    => strtotime('+1 year',$success_time),
    ]);
    return [
        'shop_id'   => $shop,
        'product_id'    => 254,
        'product_name'  => '短书高级版',
        'brief'         => '购买“短书高级会员”后，您可以在购买后至有效期内（一年），享受完整的短书高级版权限、服务和功能。',
        'thumb' => '',
        'type'  => 'permission',
        'category'  => '',
        'sku'   => 'a:1:{s:10:"properties";a:1:{i:0;a:2:{s:1:"k";s:9:"有效期";s:1:"v";s:6:"一年";}}}',
        'unit_price'    => 1999.00,
        'quantity'  => 1,
        'total'=> 1999.00,
        'meta'  => '',
        'order_no'  => $faker->numberBetween(1000000000000000,1999999999999999),
	    'success_time'=> $faker->dateTimeBetween('-3 month')->getTimestamp(),
        'create_time'   => $faker->dateTimeBetween('-3 month')->getTimestamp(),
    ];
});

$factory->define(App\Models\VersionExpire::class,function (Faker\Generator $faker) {
    return [];
});

$factory->define(App\Models\OpenPlatformPublic::class,function (Faker\Generator $faker) {

    $ids = factory(App\Models\Shop::class)->make()->select('hashid')->get();
    $shop =$ids->random()->hashid;
    return [
        'shop_id'   => $shop,
        'appid'     => 'wx'.str_random(26),
        'primitive_name'    => 'gh_'.str_random(12),
        'access_token'      => str_random(64),
        'refresh_token'  => 'refreshtoken@@@'.str_random(64),
        'old_refresh_token' => 'refreshtoken@@@'.str_random(64),
        'create_time'   =>  $faker->dateTimeBetween('-3 month')->getTimestamp(),
        'update_time' =>  $faker->dateTimeBetween('-3 month')->getTimestamp(),
        'authorizer_info' =>  '',
    ];
});

$factory->define(App\Models\OpenPlatformApplet::class,function (Faker\Generator $faker) {

    $ids = factory(App\Models\Shop::class)->make()->select('hashid')->get();
    $shop =$ids->random()->hashid;
    return [
        'shop_id'   => $shop,
        'appid'     => 'wx'.str_random(26),
        'primitive_name'    => 'gh_'.str_random(12),
        'diy_name'      => $faker->name,
        'access_token'      => str_random(64),
        'refresh_token'  => 'refreshtoken@@@'.str_random(64),
        'old_refresh_token' => 'refreshtoken@@@'.str_random(64),
        'create_time'   =>  $faker->dateTimeBetween('-3 month')->getTimestamp(),
        'update_time' =>  $faker->dateTimeBetween('-3 month')->getTimestamp(),
    ];
});
