<?php

return [
    //包配置
    'type'=>[
        'system'=>[
            'dep'=>0,
            'path'=> root_path()
        ],
        'addons'=>[
            'dep'=>1,
            'path'=> root_path() . 'app/'
        ],
        'channel'=>[
            'dep'=>1,
            'path'=> root_path() . 'public/home/'
        ]
    ],
    'config_pre' => env('redis.prefix', ''),

    'debug'=>env('app.debug', false),

    //执行sql替换使用的前缀
    'sql_from_pre'=>'ynk_',

    'sql_burst'=>false, //数据库执行分段
];