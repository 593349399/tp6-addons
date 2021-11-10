<?php
/**
 * user: peter
 * Date：2021/8/31
 * Time: 11:42
 */

namespace GdPeter\Tp6Addons\provider;

use GdPeter\Tp6Addons\exception\PackageException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Event;

/**
 * sql存储的包系统
 * Class SqlMain
 * @package GdPeter\Tp6Addons\provider
 */
class SqlMain extends MainBase
{
    //数据表，不含前缀
    protected $dbs = [
        'package'=>'package', //包
        'package_hooks'=>'package_hooks', //埋点
        'package_triggers'=>'package_triggers', //埋点实现
        'package_commands'=>'package_commands', //命令
        'package_commands_log'=>'package_commands_log', //命令日志
        'package_tasks'=>'package_tasks', //定时任务
        'package_tasks_log'=>'package_tasks_log', //定时任务日志
    ];

    public function __construct()
    {
        $dbs = Config::get('package.dbs',[]);
        if($dbs){
            $this->dbs = array_merge($this->dbs,$dbs);
        }
        parent::__construct();
    }

    /**
     * 加载触发器
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function loadTriggers()
    {
        $dbData = $this->getQueryByTable('package_triggers')->select();

        $data = [];
        foreach ($dbData as $v){
            if(!isset($data[$v['name']])){
                $data[$v['name']] = [];
            }
            $data[$v['name']][] = [$v['class'],$v['handle'] ?: 'handle'];
        }

        if($data){
            Event::listenEvents($data);
        }
    }

    /**
     * 记载命令行
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function loadCommands()
    {
        $dbData = $this->getQueryByTable('package_commands')->select();

        $data = [];
        foreach ($dbData as $v){
            $data[$v['name']] = $v['class'];
        }

        if($data){
            $this->commands($data);
        }
    }

    /**
     * 获取包信息
     * @param null $identifie
     * @return array|mixed
     * @throws PackageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
//    protected function getPackage($identifie = null)
//    {
//        $dbData = $this->getQueryByTable('package')->select()->column(null,'identifie');
//
//        if($identifie){
//            if(isset($dbData[$identifie])){
//                return $dbData[$identifie];
//            }
//
//            throw new PackageException("没有找到{$identifie}应用数据,请清除缓存后重试");
//        }else{
//            return $dbData;
//        }
//    }

    /**
     * 获取Sql句柄
     * @param $table
     * @param false $cache
     */
    private function getQueryByTable($table,$cache = true)
    {
        $db =  $this->dbs[$table];

        if($cache){
            return Db::name($db)->cache($db);
        }else{
            return Db::name($db);
        }
    }
}