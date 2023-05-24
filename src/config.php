<?php

return [
    //包配置
    'type'=>[
        'system'=>[
            'dep'=>0,
            'path'=> root_path()
        ]
    ],
    'cache_pre' => env('redis.prefix', ''),
    'debug'=>env('app.debug', false),
    'sql_from_pre'=>false,
];