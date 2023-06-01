<?php
/**
 * user: peter
 * datetime：2023-05-24 15:43:29
 */
declare (strict_types=1);

namespace Gdpeter\Tp6Addons;

use Gdpeter\Tp6Addons\command\BuildVersion;
use Gdpeter\Tp6Addons\command\SendConfig;
use Gdpeter\Tp6Addons\PackageException;
use Gdpeter\Tp6Addons\provider\Update;
use myttyy\Directory;
use myttyy\FilesFinder;

use think\App;
use think\exception\ClassNotFoundException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Event;
use think\facade\Log;
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
        'debug' => false, //debug模式不记录缓存
        'burst'=>4048*1024, //升级下载分包大小
        'type' => [],
        'cache_pre' => 'package:', //缓存前缀
        'sql_from_pre' => 'tp6_', //数据库替换前缀
        'sql_burst' => false, //数据库执行分段
        'runtime' => '' ,  //默认安装路径 root_path() . 'runtime/package/'
    ];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->initialize();
    }

    private function initialize()
    {
        $this->defaultConfig['runtime'] = root_path() . 'runtime/package/';

        //初始化包配置
        $config = array_merge($this->defaultConfig,Config::get('package'));
        Config::set($config,'package');

        //初始化安装包目录
        Directory::create($config['runtime']);
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
            'package:config' => SendConfig::class,
            'package:build' => BuildVersion::class,
        ]);
    }

    public function readXml($file)
    {
        $content = read_xml($file);
        if($content){
            return [
                'name'=>$content['application']['name'],
                'type'=>$content['application']['type'],
                'identifie'=>$content['application']['identifie'],
                'version'=>$content['application']['version'],
                'package'=>$content,
            ];
        }else{
            throw new PackageException('安装包配置文件错误',$file);
        }
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
                    try {
                        $content = $this->readXml($p['path']);
                        $content['path'] = $p['path'];
                        $content['rootPath'] = $p['dirname'] . '/';
                        $cache[$content['identifie']] = $content;
                    }catch (\Throwable $e){
                        Log::error(['msg'=>$e->getMessage(),'file'=>$p['path']]); //实时写入
                        continue;
                    }
                }
            }
            Cache::set($cacheName,$cache);
        }

        if($key == '*'){
            return $cache;
        }else{
            if(isset($cache[$key])){
                return $cache[$key];
            }else{
                throw new PackageException(2000);
            }
        }
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