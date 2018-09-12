<?php

/*
 * This file is part of Laravel Hashids.
 *
 * (c) Vincent Klaiber <hello@vinkla.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the connections below you wish to use as
    | your default connection for all work. Of course, you may use many
    | connections at once using the manager class.
    |
    */

    'default' => 'main',

    /*
    |--------------------------------------------------------------------------
    | Hashids Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the connections setup for your application. Example
    | configuration has been included, but you may add as many connections as
    | you would like.
    |
    */

    'connections' => [
        'main' => [
            'salt' => 'pl0okmnji98uhbvgy76tfcxdr54eszaw32q1',
            'length' => '12',
            'alphabet'  => 'zxcvb09876nmlkj12345'
        ],
        'column' => [
            'salt' => 'eszaw32q1pl0ouhbvgy76tfcxdr54kmnji98',
            'length' => '12',
            'alphabet'  => 'nmlkj12345zxcvb09876'
        ],
        'shop' => [
            'salt' => '1qazxsw23edcvfr45tgbnhy67ujmki89olp0',
            'length' => '18',
            'alphabet'  => 'a1b2c3d4e5f6g7h8i9j0'
        ],
        'sdk' => [
            'salt' => '211a818251da11e890f4acbc32cb692b',
            'length' => 16
        ]
    ],

];
