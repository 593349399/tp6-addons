<?php
/**
 * user: peter
 * Date：2021/8/7
 * Time: 14:00
 */
declare (strict_types=1);

namespace GdPeter\Tp6Addons;

use myttyy\FilesFinder;
use think\App;
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
    // 配置
    protected $config = [
        'debug' => false,
        'path' => [],
        'config_pre' => 'Tp6Addons:'
    ];

    protected $package = []; //所有包

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->config = array_merge($this->config,Config::get('package'));
    }

    public function register()
    {
        $this->app->bind('package',$this); //包管理实例注入
    }

    public function boot()
    {
        $this->loadEvent();
        $this->loadCommand();
    }

    //获取本地包列表
    public function getPackage($key = '*')
    {
        $rootPath = $this->app->getRootPath();

        $cacheName = $this->getCacheName('package');
        $cache = $this->isDebug() ? []: Cache::get($cacheName,[]);

        if(empty($cache)){
            foreach ($this->config['path'] as $k => $v){
                foreach ($v as $dir){
                    $package = FilesFinder::maxDepth($k)->select(["package.xml"],$rootPath . $dir)->toArray();
                    foreach ($package as $v){
                        $content = $this->read_xml($v['path']);
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

    /**
     * 读取文件内容
     * @param $filename  文件名
     * @return string 文件内容
     */
    private function read_file($filename)
    {
        $content = '';
        if (function_exists('file_get_contents')) {
            @$content = file_get_contents($filename);
        } else {
            if (@$fp = fopen($filename, 'r')) {
                @$content = fread($fp, filesize($filename));
                @fclose($fp);
            }
        }
        return $content;
    }

    /**
     * 读取xml
     * @param $str
     * @return false|mixed
     */
    private function read_xml($filename)
    {
        $content = $this->read_file($filename);
        if(!$content){
            return false;
        }
        $xml_parser = xml_parser_create();
        if (!xml_parse($xml_parser, $content, true)) {
            xml_parser_free($xml_parser);
            return false;
        } else {
            return json_decode(json_encode(simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        }
    }


    private function getCacheName($name)
    {
        return $this->config['config_pre'] . $name;
    }

    private function isDebug()
    {
        return $this->config['debug'];
    }
}