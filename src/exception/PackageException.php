<?php
/**
 * user: peter
 * Date：2021/8/9
 * Time: 11:22
 */

namespace Gdpeter\Tp6Addons\exception;


use think\Exception;

class PackageException extends Exception
{

    public function __construct($msg,$data = [])
    {
        $this->message = $msg;
        if($data){
            trace($data,'package');
            $this->setData('package',$data);
        }
    }
}