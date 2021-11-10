<?php
/**
 * user: peter
 * Date：2021/8/9
 * Time: 11:22
 */

namespace GdPeter\Tp6Addons\exception;


use think\Exception;
use think\facade\Log;

class PackageException extends Exception
{
    public function __construct($msg,$data = [])
    {
        $this->message = $msg;
        if($data){
            Log::write($data,'package');
            $this->setData('package',$data);
        }
    }
}