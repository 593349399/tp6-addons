<?php
/**
 * user: peter
 * Date：2021/8/7
 * Time: 14:00
 */
declare (strict_types=1);

namespace GdPeter\Tp6Addons;

use GdPeter\Tp6Addons\command\SendConfig;
use GdPeter\Tp6Addons\exception\PackageException;
use GdPeter\Tp6Addons\provider\Update;
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
 * @package GdPeter\Tp6Addons
 */
class Service extends BaseService
{
    // 默认配置
    protected $defaultConfig = [
        'debug' => false,
        'path' => [],
        'config_pre' => 'Tp6Addons:',
        'provider' => [
        ]
    ];

    //注入服务
    protected $defaultProvider = [
        'update'=>Update::class
    ];

    public function __construct(App $app)
    {
        $this->app = $app;
        $config = array_merge($this->defaultConfig,Config::get('package'));
        $config['provider'] = array_merge($this->defaultProvider,$config['provider']);
        Config::set($config,'package');
    }

    public function register()
    {
        $this->app->bind('package',$this);
    }

    public function boot()
    {
        $provider = Config::get('package.provider',[]);
        foreach ($provider as $k => $v){
            if(!class_exists($v)){
                throw new ClassNotFoundException($v . '类不存在',$v);
            }
            $this->app->bind('package:'.$k,$v);
            if(method_exists($v,'init')){
                $v::init();
            }
        }
        $this->loadEvent();
        $this->loadCommand();
        $this->commands([
            'package:config' => SendConfig::class
        ]);
    }

    //获取本地包列表
    public function getPackage($key = '*')
    {
        $rootPath = $this->app->getRootPath();

        $cacheName = $this->getCacheName('package');
        $cache = $this->isDebug() ? []: Cache::get($cacheName,[]);

        if(empty($cache)){
            $path = Config::get('package.path');
            foreach ($path as $k => $v){
                foreach ($v as $dir){
                    $package = FilesFinder::maxDepth($k)->select(["package.xml"],$rootPath . $dir)->toArray();
                    foreach ($package as $v){
                        $content = read_xml($v['path']);
                        if($content && !empty($content['application']['identifie']) && !empty($content['application']['version'])){
                            $idx = $content['application']['identifie'];
                            $cache[$idx] = [
                                'identifie'=>$idx,
                                'version'=>$content['application']['version'],
                                'path'=>$v['path'],
                                'rootPath'=>$v['dirname'],
                                'package'=>$content,
                            ];
                        }else{
                            trace('非法包：'.$v['path'],'alert');
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
        return Config::get('package.config_pre') . $name;
    }

    private function isDebug()
    {
        return Config::get('package.debug');
    }

    //获取容器实例
    public function get(string $name = '', array $args = [], bool $newInstance = false)
    {
        $provider = Config::get('package.provider',[]);

        if(isset($provider[$name])){
            return app('package:'.$name, $args, $newInstance);
        }

        throw new PackageException('没有找到容器'.$name);
    }
}