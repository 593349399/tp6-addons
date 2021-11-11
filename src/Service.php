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
use GdPeter\Tp6Addons\provider\SqlMain;

use think\App;
use think\exception\ClassNotFoundException;
use think\facade\Config;
use think\Service as BaseService;

/**
 * 包服务
 * Class Service
 * @package GdPeter\Tp6Addons
 */
class Service extends BaseService
{
    protected $app;

    // 默认配置
    protected $defaultConfig = [
        'cache_pre' => 'package:', //通用缓存前缀
        'path' => [], //包数据
        'provider' => [] //载入容器
    ];

    //默认注入
    protected $defaultProvider = [
        'main'=>SqlMain::class, //主业务使用数据库记录
    ];

    public function __construct(App $app)
    {
        $this->app = $app;

        //初始化配置，直接使用tp6的config
        $config = array_merge($this->defaultConfig,Config::get('package'));
        $config['provider'] = array_merge($this->defaultProvider,$config['provider']);
        Config::set($config,'package');
    }

    public function register()
    {
        $this->app->bind('package',$this); //注入package
    }

    public function boot()
    {
        $provider = Config::get('package.provider',[]);
        foreach ($provider as $k => $v){
            if(!is_object($v) && !class_exists($v)){
                throw new ClassNotFoundException($v . '类不存在',$v);
            }
            $this->app->bind('package_provider_pre:'.$k,$v); //绑定package内的包，并调用初始化方法
            if(method_exists($v,'init')){
                $v::init();
            }
        }

        $this->commands([
            'package:config' => SendConfig::class, //配置
        ]);
    }

    //获取容器实例
    public function get(string $name = '', array $args = [], bool $newInstance = false)
    {
        $provider = Config::get('package.provider',[]);

        if(isset($provider[$name])){
            return $this->app->make('package_provider_pre:'.$name, $args, $newInstance);
        }

        throw new PackageException('没有找到容器'.$name);
    }
}