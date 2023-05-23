<?php
/**
 * user: peter
 * Date：2021/8/9
 * Time: 11:22
 */

namespace Gdpeter\Tp6Addons;

use think\Exception;

class PackageException extends Exception
{
    public function __construct($msg,$data = [])
    {
        $this->message = $msg;
        !empty($data) && $this->setData('package',$data);
    }
}