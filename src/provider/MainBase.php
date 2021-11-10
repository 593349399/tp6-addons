<?php
/**
 * 著作权归深圳市桔子互联信息技术有限公司所有
 * user: peter
 * Date：2021/11/10
 * Time: 10:29
 */

namespace GdPeter\Tp6Addons\provider;


use think\Console;

abstract class MainBase
{
    public function __construct()
    {
        $this->loadTriggers();
        $this->loadCommands();
    }

    public static function init()
    {
        app()->get('package')->get('main'); //初始化本服务
    }

    /**
     * 加载监听
     * @return mixed
     */
    abstract protected function loadTriggers();

    /**
     * 加载命令
     * @return mixed
     */
    abstract protected function loadCommands();

    /**
     * 刷新数据
     * @return mixed
     */
    abstract protected function refresh();


    final protected function getPackage(){
        
    }

    /**
     * 添加指令
     * @access protected
     * @param array|string $commands 指令
     */
    final protected function commands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        Console::starting(function (Console $console) use ($commands) {
            $console->addCommands($commands);
        });
    }
}