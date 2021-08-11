<?php
/**
 * 著作权归深圳市桔子互联信息技术有限公司所有
 * user: peter
 * Date：2021/8/11
 * Time: 15:13
 */

namespace GdPeter\Tp6Addons\exception;


use think\Exception;

class UpdateException extends Exception
{
    const LEVEL_PASS = 1; //可以跳过的更新错误
    const LEVEL_DIE = 2; //不可以跳过的更新错误

    private $level; //错误类型

    public function __construct($msg,$level = self::LEVEL_DIE)
    {
        $this->message = $msg;
        $this->setLevel($level);
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function setLevel($level)
    {
        $this->level = $level;
    }
}