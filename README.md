### 基于THINKPHP6的版本控制拓展
特点：
1. 使用git进行版本管理，`php think package:build 1.0.0 1.0.1` 即可生成1.0.1版本的增量补丁
2. 主要的一个函数是`installPackage('system','1.0.1','补丁地址','补丁MD5')`，从下载到安装一条龙
   1. 使用文件锁保证多进程并发情况下只会执行一次
   2. 使用md5和配置的检查对包的完整性进行了验证
   3. 支持包的断点下载，保证网络不好的时候下载补丁失败
   4. 动态配置每次更新的执行文件/SQL文件
   5. 动态配置不同包的event、command
   6. 完整的执行日志
   7. ......

升级示例：
> 示例仅为系统补丁升级流程！！！安装流程、插件流程劳驾您举一反三

> git、tp6得熟悉，示例干得啥理解了就行，别原搬示例代码

> 示例分支：master、1.0.0、1.0.1，注意分支一定要push到origin，git diff的时候自动加了origin的前缀

1. `git checkout master` 切换至主分支
2. 安装拓展 `composer require gdpeter/tp6-addons`
3. 配置config/package.php
```php
return [
    'type'=>[ //这个是分组，package.xml配置里面可以配置type
        'system'=>[ //系统包
            'dep'=>0, //深度 0表示这个目录就是一个项目
            'path'=> root_path()
        ],
//        'addons'=>[ //插件包
//            'dep'=>1, // 1表示这个目录里面有多个项目
//            'path'=> root_path() . 'addons/'
//        ],
        'debug'=>env('app.debug', false), //debug模式不开启缓存
        'burst'=>4048*1024, //补丁下载分包大小
        'sql_from_pre' => 'tp6_', //数据库替换前缀
        //...详细配置请查看Gdpeter\Tp6Addons\Service服务注入的$defaultConfig
    ],
]
```
4. 配置1.0.0版本的 package.xml到包的目录，这里system包是在根目录
```php
<?xml version="1.0" encoding="utf-8"?>
<manifest>
    <application>
        <name><![CDATA[淘宝]]></name>
        <type><![CDATA[system]]></type>
        <identifie><![CDATA[system]]></identifie>
        <version><![CDATA[1.0.0]]></version>
        <description><![CDATA[亚洲较大的网上交易平台，提供各类服饰、美容、家居、数码、话费/点卡充值… 数亿优质商品，同时提供担保交易(先收货后付款)等安全交易保障服务，并由商家提供退货承诺、破损补寄等消费...]]></description>
        <author><![CDATA[peter]]></author>
    </application>
    <install><![CDATA[安装.sql]]></install>
    <uninstall><![CDATA[]]></uninstall>
    <upgrade><![CDATA[]]></upgrade>
</manifest> 
```
5. 自行新建 安装.sql，也可以改成 安装.php也会执行,或者不创建
6. `git checkout -b 1.0.0 master` 切换至1.0.0分支并推送，此时1.0.0版本已经生成，包是完整的1.0.0zip，可用于安装（不演示）
7. 切换至master分支，同上配置1.0.1版本的 package.xml，新建分支1.0.1，推送到git origin
```php
<?xml version="1.0" encoding="utf-8"?>
<manifest>
    <application>
        <name><![CDATA[淘宝]]></name>
        <type><![CDATA[system]]></type>
        <identifie><![CDATA[system]]></identifie>
        <version><![CDATA[1.0.1]]></version>
        <description><![CDATA[亚洲较大的网上交易平台，提供各类服饰、美容、家居、数码、话费/点卡充值… 数亿优质商品，同时提供担保交易(先收货后付款)等安全交易保障服务，并由商家提供退货承诺、破损补寄等消费...]]></description>
        <author><![CDATA[peter]]></author>
    </application>
    <install><![CDATA[安装.sql]]></install>
    <uninstall><![CDATA[]]></uninstall>
    <upgrade><![CDATA[更新.sql]]></upgrade>
</manifest> 
```
8. 此时git origin 有三个分支，master、1.0.0、1.0.1，使用命令生成1.0.1补丁`php think package:build 1.0.0 1.0.1`，查看1.0.1版本是否生成：package_build/1.0.1.zip
9. 任意处理1.0.1.zip到cdn或者某个可以下载的地方即可，获取其md5_file值，此时有url和md5了

在某段升级代码中是这样运行的，首先它是1.0.0版本了，然后执行1.0.1的更新
```php
//注意这里的system是包名identifie不是分组type，例如你是dep为1的type为addons中有一个插件名为coupon，这里是coupon而非addons
$res = app(Package::class)->installPackage('system','1.0.1','http://xxx/1.0.1.zip','md5xxxxxxx');

if($res){
    $this->success('升级成功');
}else{
    $this->error('升级进程正在进行中！');
}
```

> 就是这么简单就升级成功了！！！！

> app('package') 可以获取service服务，具体看代码Gdpeter\Tp6Addons\Service

> app(Package::class) 可以获取安装服务，具体看代码Gdpeter\Tp6Addons\library\Package