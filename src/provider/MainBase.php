<?php
/**
 * 著作权归深圳市桔子互联信息技术有限公司所有
 * user: peter
 * Date：2021/11/10
 * Time: 10:29
 */

namespace GdPeter\Tp6Addons\provider;


use myttyy\FilesFinder;
use think\Console;
use think\facade\Config;
use think\facade\Log;

abstract class MainBase
{
    private $data = false;

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
     * 刷新单个包数据
     * @param string $key
     * @return mixed
     */
    abstract protected function refresh($key);

    /**
     * 从原始数据中心加载包
     * @param string $key
     * @return array|bool|mixed
     */
    final protected function getPackageByPath($key = '*'){

        $data = $this->data;

        if(false === $data){
            $data = [];
            $path = Config::get('package.path');
            foreach ($path as $k => $v){
                $packages = FilesFinder::maxDepth($v['dep'])->select(["package.xml"],$v['path'])->toArray();
                foreach ($packages as $package){
                    $content = read_xml($package['path']);
                    if($content && !empty($content['application']['name']) && !empty($content['application']['identifie']) && !empty($content['application']['version'])){
                        $idx = $content['application']['identifie'];
                        $data[$idx] = [
                            'identifie'=>$idx,
                            'version'=>$content['application']['version'],
                            'path'=>$package['path'],
                            'rootPath'=>$package['dirname'],
                            'package'=>$content,
                        ];
                    }else{
                        Log::write('非法包：'.$v['path'],'package');
                    }
                }
            }
            $this->data = $data;
        }

        return $key == '*' ? $data : ($data[$key] ?? false);
    }

    /**
     * 根据包解析hook参数
     * @param $package
     */
    final protected function analysisHook($package){
    }

    /**
     * 解析command参数
     * @param $package
     */
    final protected function analysisCommand($package,$extends = []){
        $data = [];
        if(!empty($package['package']['command']['item'])){
            foreach ($package['package']['command']['item'] as $e){
                if(isset($e['@attributes'])){
                    $e = $e['@attributes'];
                }

                if(!empty($e['name']) && !empty($e['class'])){
                    $data[] = array_merge($extends,[
                        'name'=>$e['name'],
                        'class'=>$e['class'],
                    ]);
                }
            }
        }
        return $data;
    }

    /**
     * 解析listen参数
     * @param $package
     */
    final protected function analysisListen($package,$extends = []){
        $data = [];
        if(!empty($package['package']['listen']['item'])){
            foreach ($package['package']['listen']['item'] as $e){
                if(isset($e['@attributes'])){
                    $e = $e['@attributes'];
                }

                if(!empty($e['name']) && !empty($e['class'])){
                    $data[] = array_merge($extends,[
                        'name'=>$e['name'],
                        'class'=>$e['class'],
                        'handle'=>$e['handle'] ?? 'handle',
                    ]);
                }
            }
        }
        return $data;
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