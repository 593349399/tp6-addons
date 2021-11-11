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
        $this->dbs = array_merge($this->dbs, (array) Config::get('package.dbs',[]));
        parent::__construct();
    }

    protected function loadTriggers()
    {
        $dbData = $this->getQueryByTable('package_triggers')->select();
        $data = [];
        foreach ($dbData as $v){
            if(!isset($data[$v['name']])){
                $data[$v['name']] = [];
            }
            $data[$v['name']][] = [$v['class'],$v['handle']];
        }

        if($data){
            Event::listenEvents($data);
        }
    }

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

    protected function refresh($key){
        $oldPackage = $this->getQueryByTable('package',false)->where('identifie',$key)->find();
        $package = $this->getPackageByPath($key); //获取本地包数据

        if(!empty($oldPackage) || !empty($package)){
            if(empty($oldPackage)){
                //$package不为空
                $packageId = $this->getQueryByTable('package')->save($this->getPackageData($package));
                $commands = $this->analysisCommand($package,['package_id'=>$packageId]);
                $listens = $this->analysisListen($package,['package_id'=>$packageId]);
                $this->getQueryByTable('package_commands')->insertAll($commands);
                $this->getQueryByTable('package_triggers')->insertAll($listens);
            }else if(empty($package)){
                //$oldPackage不为空
                $this->getQueryByTable('package')->where('id',$oldPackage['id'])->save([
                    'status'=>2
                ]);
                $this->getQueryByTable('package_commands')->where('package_id',$oldPackage['id'])->delete();
                $this->getQueryByTable('package_triggers')->where('package_id',$oldPackage['id'])->delete();
            }else{
                //都不为空
                $update = array_diff($this->getPackageData($package),$oldPackage);
                //判断是否有字段更新了
                if($update){
                    $this->getQueryByTable('package')->where('id',$oldPackage['id'])->save($update);
                    $this->getQueryByTable('package_commands',false)->where('package_id',$oldPackage['id'])->delete();
                    $this->getQueryByTable('package_triggers',false)->where('package_id',$oldPackage['id'])->delete();
                    $commands = $this->analysisCommand($package,['package_id'=>$oldPackage['id']]);
                    $listens = $this->analysisListen($package,['package_id'=>$oldPackage['id']]);
                    $this->getQueryByTable('package_commands')->insertAll($commands);
                    $this->getQueryByTable('package_triggers')->insertAll($listens);
                }
            }
        }
    }

    /**
     * 根据本地包获取package表数据
     * @param $package
     * @return array
     */
    private function getPackageData($package)
    {
        $res = [
            'name'=>$package['package']['application']['name'],
            'identifie'=>$package['package']['application']['identifie'],
            'version'=>$package['package']['application']['version'],
            'ability'=>$package['package']['application']['ability'] ?? '',
            'type'=>$package['package']['application']['type'] ?? '',
            'description'=>$package['package']['application']['description'] ?? '',
            'author'=>$package['package']['application']['author'] ?? '',
            'statuss'=>1,
        ];
        unset($package['package']['application']);
        $res['package'] = serialize($package);

        return $res;
    }

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