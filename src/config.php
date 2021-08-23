<?php

return [
    //包配置
    'type'=>[
        'system'=>[
            'dep'=>0,
            'path'=>app()->getRootPath()
        ],
        'addons'=>[
            'dep'=>1,
            'path'=>app()->getRootPath() . 'app/'
        ],
        'channel'=>[
            'dep'=>1,
            'path'=>app()->getRootPath() . 'public/home/'
        ]
    ],
    'debug'=>false,

    //执行sql替换使用的前缀
    'database_from_pre'=>'jz_',

    //载入的容器
    'provider'=>[
    ]

];