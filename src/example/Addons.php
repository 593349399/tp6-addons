<?php
/**
 * user: peter
 * Date：2021/8/9
 * Time: 11:09
 */
declare(strict_types=1);

namespace Gdpeter\Tp6Addons\example;

use think\App;

/**
 * 插件基类
 * Class Addons
 * @package Gdpeter\Tp6Addons
 */
abstract class Addons
{
    // app 容器
    protected $app;
    // 请求对象
    protected $request;
    // 插件名称
    protected $name;
    // 插件信息
    protected $addon;

    /**
     * 插件构造函数
     * Addons constructor.
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->name = $this->getName();
        $this->addon = $this->app->get('package')->getPackage($this->name);
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}

    /**
     * 获取插件标识
     * @return mixed|null
     */
    final protected function getName()
    {
        $class = get_class($this);
        list(, $name, ) = explode('\\', $class);
        $this->request->addon = $name;

        return $name;
    }
}