<?php
/**
 * user: peter
 * Date：2021/8/31
 * Time: 11:42
 */

namespace GdPeter\Tp6Addons\provider;


use think\App;

class Package
{
    private $db;
    private $package;

    public function __construct(App $app)
    {
        $this->package = $app->get('package');
    }

    //获取包数据
    public function getPackage()
    {

    }
}