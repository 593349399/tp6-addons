<?php
/**
 * user: peter
 * Date：2021/8/10
 * Time: 18:18
 */

namespace Gdpeter\Tp6Addons\exception;


use think\Exception;

class PackageSqlBurstException extends Exception
{
    private $scale; //分段百分比，整数

    public function __construct($msg,$scale)
    {
        $this->scale   = $scale;
        $this->error   = $msg;
        $this->message = is_array($msg) ? implode(PHP_EOL, $msg) :  (string)$msg;
    }

    public function getScale()
    {
        return $this->scale;
    }
}