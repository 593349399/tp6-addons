<?php
/**
 * user: peter
 * Date：2021/8/9
 * Time: 11:22
 */

namespace GdPeter\Tp6Addons\exception;


use think\Exception;

class PackageException extends Exception
{

    public function __construct($msg,$data = [])
    {
        $this->message = $msg;
        $this->setData('package',$data);
    }
}