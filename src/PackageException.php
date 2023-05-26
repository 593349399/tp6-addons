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
        1=>'package error', //默认错误信息
        1000=>'创建或打开本地文件失败!',
        1001=>'已有相关进程操作执行下载本文件!',
        1002=>'原文件已下载完成,请勿重复下载!',
        1003=>'安装包不存在，请先现在安装包!',
        1004=>'安装包验证错误，请重新安装!',
    ];

    public function __construct($code = 1,$data=null)
    {
        if(is_numeric($code)){
            $this->code = $code;
            if(isset(self::ERROR[$code])){
                $this->message = self::ERROR[$code];
            }else{
                $this->message = self::ERROR[1];
            }
        }else{
            $this->code = 1;
            $this->message = $code;
        }

        !empty($data) && $this->setData('package',$data);
    }
}