<?php
/**
 * user: peter
 * Date：2021/8/7
 * Time: 14:00
 */
declare (strict_types=1);

namespace Gdpeter\Tp6Addons;

use Gdpeter\Tp6Addons\command\SendConfig;
use Gdpeter\Tp6Addons\exception\PackageException;
use Gdpeter\Tp6Addons\provider\Update;
use myttyy\FilesFinder;

use think\App;
use think\exception\ClassNotFoundException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Event;
use think\Service as BaseService;

/**
 * 包服务
 * Class Service
 * @package Gdpeter\Tp6Addons
 */
class Service extends BaseService
{
    // 默认配置
    protected $defaultConfig = [
        'debug' => false,
        'type' => [],
        'cache_pre' => 'Tp6Addons:', //缓存前缀
        'sql_from_pre' => 'tp6_', //数据库替换前缀
        'sql_burst' => false, //数据库执行分段
    ];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->initialize();
    }

    private function initialize()
    {
        //初始化包配置
        $config = array_merge($this->defaultConfig,Config::get('package'));
        Config::set($config,'package');
    }

    public function register()
    {
        $this->app->bind('package',$this);
    }

    public function boot()
    {
        $this->loadEvent();
        $this->loadCommand();
        $this->commands([
            'package:config' => SendConfig::class, //配置推送命令
        ]);
    }

    //获取本地包已安装列表数据
    public function getPackage($key = '*')
    {
        $cacheName = $this->getCacheName('package');
        $cache = $this->isDebug() ? []: Cache::get($cacheName,[]);

        if(empty($cache)){
            $type = Config::get('package.type');
            foreach ($type as $v){
                $package = FilesFinder::maxDepth($v['dep'])->select(["package.xml"],$v['path'])->toArray();
                foreach ($package as $p){
                    $content = read_xml($p['path']);
                    if($content){
                        try {
                            $idx = $content['application']['identifie'];
                            $cache[$idx] = [
                                'identifie'=>$idx,
                                'version'=>$content['application']['version'],
                                'path'=>$p['path'],
                                'rootPath'=>$p['dirname'],
                                'package'=>$content,
                            ];
                        }catch (\Throwable $e){
                            continue;
                        }
                    }
                }
            }
            Cache::set($cacheName,$cache);
        }

        return $key == '*' ? $cache : ($cache[$key] ?? false);
    }

    //清除缓存
    public function deleteCache()
    {
        Cache::delete($this->getCacheName('package'));
        Cache::delete($this->getCacheName('hook'));
        Cache::delete($this->getCacheName('command'));
        return $this;
    }

    //加载全局命令
    private function loadCommand()
    {
        $cacheName = $this->getCacheName('command');
        $cache = $this->isDebug() ? []: Cache::get($cacheName,[]);

        if(empty($cache)){
            $package = $this->getPackage(); //加载所有包数据
            foreach ($package as $v){
                if(!empty($v['package']['command']['item'])){
                    foreach ($v['package']['command']['item'] as $e){
                        if(isset($e['@attributes'])){
                            $e = $e['@attributes'];
                        }

                        $cache[$e['name']] = $e['class'];
                    }
                }
            }

            Cache::set($cacheName,$cache);
        }

        if(!empty($cache)){
            $this->commands($cache);
        }
    }

    //加载全局事件
    private function loadEvent()
    {
        $cacheName = $this->getCacheName('hook');
        $cache = $this->isDebug() ? []: Cache::get($cacheName,[]);

        if(empty($cache)){
            $package = $this->getPackage(); //加载所有包数据
            foreach ($package as $v){
                if(!empty($v['package']['listen']['item'])){
                    foreach ($v['package']['listen']['item'] as $e){
                        if(isset($e['@attributes'])){
                            $e = $e['@attributes'];
                        }
                        if(!isset($cache[$e['name']])){
                            $cache[$e['name']] = [];
                        }

                        $cache[$e['name']][] = [$e['class'],$e['handle'] ?? 'handle'];
                    }
                }
            }

            Cache::set($cacheName,$cache);
        }

        if(!empty($cache)){
            Event::listenEvents($cache);
        }
    }

    private function getCacheName($name)
    {
        return Config::get('package.cache_pre') . $name;
    }

    private function isDebug()
    {
        return Config::get('package.debug');
    }
}