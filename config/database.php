<?php

return [

    'fetch' => PDO::FETCH_CLASS,

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '10.0.1.31'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DEFAULT_DB_DATABASE', 'hgc_tech'),
            'username' => env('DEFAULT_DB_USERNAME', 'root'),
            'password' => env('DEFAULT_DB_PASSWORD', 'hogesoft'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => 'hg_',
            'strict' => false,
            'engine' => null,
        ],
        
        'djangodb' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '10.0.1.31'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DEFAULT_DB_DATABASE', 'hgc_tech'),
            'username' => env('DEFAULT_DB_USERNAME', 'root'),
            'password' => env('DEFAULT_DB_PASSWORD', 'hogesoft'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ],

        'oauth2' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '10.0.1.31'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('OAUTH_DB_DATABASE', 'hgc_authorize'),
            'username' => env('OAUTH_DB_USERNAME', 'root'),
            'password' => env('OAUTH_DB_PASSWORD', 'hogesoft'),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => 'hg_',
            'strict' => false,
            'engine' => null,
        ],

        'log' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '10.0.1.31'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('LOG_DB_DATABASE', 'hgc_duanshu_log'),
            'username' => env('LOG_DB_USERNAME', 'root'),
            'password' => env('LOG_DB_PASSWORD', 'hogesoft'),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => 'hg_',
            'strict' => false,
            'engine' => null,
        ],

    ],

    'migrations' => 'migrations',

    'redis' => [

        'cluster' => false,

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('DEFAULT_REDIS_DATABASE', 8),
        ],

        'session' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' =>  env('SESSION_REDIS_DATABASE', 9),
        ],

    ],

];
