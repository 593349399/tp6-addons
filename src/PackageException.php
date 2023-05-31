<?php
/**
 * user: peter
 * datetime：2023-05-24 15:43:29
 */

namespace Gdpeter\Tp6Addons;

use think\Exception;

class PackageException extends Exception
{
    const ERROR = [
        1000=>'已有相关进程操作执行下载本文件!',
        1001=>'系统已经升级成功!',
        1002=>'安装包MD5验证失败，请重新安装!',

        2000=>'配置读取失败，请刷新缓存重试！',
    ];

    public function __construct($code = 0,$data=null)
    {
        if(is_numeric($code)){
            $this->code = $code;
            $this->message = self::ERROR[$code] ?? '';
        }else{
            $this->code = 0;
            $this->message = $code;
        }

        !empty($data) && $this->setData('package',$data);
    }
}