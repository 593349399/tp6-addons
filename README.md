### 基于THINKPHP6的系统增量升级拓展
以下是一个增量迭代的过程
1. 在 config/package.php 进行包的配置
```php
//在配置目录处配置有几种类型的包和路径
return [
    'type'=>[
        'system'=>[ //系统包
            'dep'=>0, //深度 0表示这个目录就是一个项目
            'path'=> root_path()
        ],
        'addon'=>[ //插件包
            'dep'=>1, // 1表示这个目录里面有多个项目
            'path'=> root_path() . 'addons/'
        ],
        
        //...自定义
    ],
]
```

2. 在某段升级代码中直接运行
```php
//在配置目录处配置有几种类型的包和路径
$res = app(Package::class)->installPackage('system',$params['version'],$params['url'],$params['md5']);

if($res){
    $this->success('升级成功');
}else{
    $this->error('升级进程正在进行中！');
}
```

> 就是这么简单就升级成功了！！！！


> 使用GIT DIFF进行增量包制作，拓展提供了一键生成命令
```php
//通过下面命令就可以直接得到一个差量升级包
php think package:build 老版本v1 新版本v2
```