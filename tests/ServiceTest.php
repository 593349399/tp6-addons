<?php
/**
 * user: peter
 * Date：2021/8/21
 * Time: 11:50
 */

namespace GdPeter\test;

use GdPeter\Tp6Addons\Service;
use Mockery as m;
use myttyy\FilesFinder;
use PHPUnit\Framework\TestCase;
use think\App;
use think\facade\Config;

class SomeBind extends \think\Service
{
    public static function init()
    {
    }
}

class ServiceTest extends TestCase
{

    /** @var App */
    protected $app;

    /**
     * 基境:
     * setUp:每个测试方法初始化环境
     * tearDown:每个测试方法结束还原环境
     * setUpBeforeClass：第一个测试运行之前
     * tearDownAfterClass：最后一个测试运行之后调用
     */
    protected function setUp()
    {
        $this->app = new App();
        $this->app->initialize();
        $this->app->register(Service::class);
    }

    protected function tearDown(): void
    {
        m::close();
    }

    /**
     * 测试绑定获取容器
     */
    public function testBind()
    {
        $someBind = m::mock(SomeBind::class);
        $someBind->shouldReceive('init')->once();

        Config::set(['provider'=>[
            'somebind'=>$someBind
        ]],'package');

        $this->app->bootService(Service::class);

        $service = $this->app->getService(Service::class);

        $this->assertEquals($someBind, $service->get('somebind'));
    }
}