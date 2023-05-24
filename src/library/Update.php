<?php
/**
 * user: peter
 * Date：2021/8/11
 * Time: 9:11
 */

namespace Gdpeter\Tp6Addons\library;

use Gdpeter\Tp6Addons\PackageException;
use Gdpeter\Tp6Addons\library\DownloadTool;
use myttyy\Directory;
use myttyy\File;
use think\App;
use think\facade\Config;
use think\facade\Event;

/**
 * 包安装脚本管理
 * Class Package
 * @package Gdpeter\Tp6Addons
 */
class Update
{
    private $app;
    private $package;
    private $sqlBurst;
    private $sqlFromPre;
    private $sqlToPre;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->package = $this->app->get('package');
        $this->sqlBurst = Config::get('package.sql_burst',false); //暂不支持
        $this->sqlFromPre = Config::get('package.sql_from_pre',false);
        $this->sqlToPre = Config::get('database.connections.mysql.prefix');
    }

    //设置数据库分段执行条数
    public function setSqlBurst($sqlBurst)
    {
        $this->sqlBurst = $sqlBurst;
        return $this;
    }

    //设置数据库分段执行条数
    public function setSqlFromPre($sqlFromPre)
    {
        $this->sqlFromPre = $sqlFromPre;
        return $this;
    }

    //设置数据库分段执行条数
    public function setSqlToPre($sqlToPre)
    {
        $this->sqlToPre = $sqlToPre;
        return $this;
    }

    /**
     * 执行指定包版本的安装
     * @param $identifie
     * @param $version
     * @return void
     */
    public function install($identifie,$version)
    {
        $this->run($identifie,$version,'install');
    }

    /**
     * 执行指定包版本的更新
     * @param $identifie
     * @param $version
     * @return void
     */
    public function upgrade($identifie,$version)
    {
        $this->run($identifie,$version,'upgrade');
    }

    /**
     * 执行指定包版本的卸载
     * @param $identifie
     * @param $version
     * @return void
     */
    public function uninstall($identifie,$version)
    {
        $this->run($identifie,$version,'uninstall');
    }

    /**
     * 执行操作
     * @param $identifie
     * @param $version
     * @param $action
     * @return void
     * @throws PackageException
     */
    private function run($identifie,$version,$action)
    {
        $exception = compact('identifie','version','action');

        $package = $this->package->getPackage($identifie);
        if(!$package){
            throw new PackageException('安装包配置错误或不存在',$exception);
        }

        if($package['version'] != $version){
            $exception['package'] = $package;
            throw new PackageException('更新版本和本地版本不一致，清除缓存后重试',$exception);
        }

        //不限制内存
        ini_set('memory_limit','51200M');
        //不限制时间
        set_time_limit(0);

        $actions = !empty($package['package'][$action]) ? (is_array($package['package'][$action]) ? $package['package'][$action] : [$package['package'][$action]]) : [];

        //循环判断$item类型
        foreach ($actions as $item){
            $actionPath = $package['rootPath'] . $item;
            if(is_file($actionPath)){
                //加载文件
                if(false !== strpos($actionPath,'.sql')){
                    $this->readSql($actionPath);
                }else{
                    require_once $actionPath;
                }
            }
        }
    }

    /**
     * 从sql文件获取纯sql语句
     * @param  string $sqlFile sql文件路径
     * @return mixed
     */
    public function readSql($sqlFile = '')
    {
        if (!file_exists($sqlFile)) {
            return false;
        }

        // 读取sql文件内容
        $handle = read_file($sqlFile);

        // 執行sql语句
        $handle = $this->runSql($handle);

        return $handle;
    }

    /**
     * 执行sql语句
     * @param  string $content sql内容
     */
    public function runSql($content = '')
    {
        if ($content != '') {
            // 纯sql内容
            $pure_sql = [];
            // 多行注释标记
            $comment = false;
            // 按行分割，兼容多个平台
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $content = explode("\n", trim($content));
            // 循环处理每一行
            foreach ($content as $key => $line) {
                // 跳过空行
                if ($line == '') {
                    continue;
                }
                // 跳过以#或者--开头的单行注释
                if (preg_match("/^(#|--)/", $line)) {
                    continue;
                }
                // 跳过以/**/包裹起来的单行注释
                if (preg_match("/^\/\*(.*?)\*\//", $line)) {
                    continue;
                }
                // 多行注释开始
                if (substr($line, 0, 2) == '/*') {
                    $comment = true;
                    continue;
                }
                // 多行注释结束
                if (substr($line, -2) == '*/') {
                    $comment = false;
                    continue;
                }
                // 多行注释没有结束，继续跳过
                if ($comment) {
                    continue;
                }
                // 替换表前缀
                if ($this->sqlFromPre) {
                    $line = str_replace('`' . $this->sqlFromPre, '`' . $this->sqlToPre, $line);
                }
                // 替换INSERT INTO
                $line = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $line);
                // sql语句
                array_push($pure_sql, $line);
            }

            // 以数组形式返回sql语句
            $pure_sql = implode("\n", $pure_sql);
            $pure_sql = explode(";\n", $pure_sql);

            foreach ($pure_sql as $key => $value){
                $errorNum = 2; //失败重试次数
                while ($errorNum--){
                    try {
                        \think\facade\Db::execute($value);
                        continue 2; //成功跳出当前循环
                    } catch (\Throwable $e) {
                        if(!$errorNum){
                            write_package_log(['msg'=>$e->getMessage(),'error_sql'=>$value]);
                        }
                    }
                }
            }
        }
    }

    /**
     * 整包下载并执行安装或更新，支持断点续传、文件完整校验、分包下载、备份还原
     * @param $identifie
     * @param $url
     * @param false $md5
     * @param float|int $burst
     */
    public function installFullPackage($identifie,$version,$url,$md5 = false,$burst = 4048*1024)
    {
        //读取本地是否有此安装包
        $package = $this->package->getPackage($identifie);

        //错误日志记录数据
        $log = compact('identifie','version','url','md5','burst','package');

        //有本地包表示更新请求
        if($package && $package['version'] == $version){
            throw new PackageException('已经更新至'.$version.'版本，请勿重复操作！',$log);
        }

        $zipRuntimePath = $this->app->getRuntimePath() . 'package_zip/'; //zip包根目录
        $unzipRuntimePath = $this->app->getRuntimePath() . "package_unzip/{$identifie}/"; //解压根目录
        $bfRuntimePath = $this->app->getRuntimePath() . "package_bf/{$identifie}/"; //备份zip包根目录
        Directory::create($zipRuntimePath); //创建目录
        Directory::create($unzipRuntimePath); //创建目录
        Directory::create($bfRuntimePath); //创建目录


        $zipFile = $zipRuntimePath . $identifie . '.zip'; //临时安装zip名称
        //没有文件或者有文件需要校验
        if(!is_file($zipFile) || ($md5 && md5_file($zipFile) != $md5)){
            //下载文件
            (new DownloadTool())->setUrl($url)->setBurst($burst)->saveFile($zipFile);
        }

        try {
            if($md5 && md5_file($zipFile) != $md5){
                throw new PackageException("安装包下载不完整请重试",$log);
            }

            if(!$this->unzip($zipFile,$unzipRuntimePath)){
                throw new PackageException("安装包解压失败请重试",$log);
            }

            //读取包xml
            $xml = read_xml($unzipRuntimePath . 'package.xml');
            if($xml && !empty($xml['application']['identifie']) && !empty($xml['application']['version'])){
                if($xml['application']['identifie'] != $identifie){
                    throw new PackageException("远程包identifie配置错误",$log);
                }
                if($xml['application']['version'] != $version){
                    throw new PackageException("远程包version配置错误",$log);
                }
            }else{
                throw new PackageException("远程包配置错误,不存在identifie和version",$log);
            }
        }catch (\Throwable $e){
            File::delFile($zipFile); //删除zip包重新下载
            Directory::del($unzipRuntimePath); //删除解压目录数据
            throw $e;
        }

        //所有都没错，执行替换文件
        if($package){
            $action = 'upgrade';
            $movePath = $package['rootPath'];
        }else{
            $action = 'install';
            $type = Config::get('package.type');
            if(empty($xml['application']['type']) || !isset($type[$xml['application']['type']])){
                throw new PackageException("远程包type或本地package.type配置错误",$log);
            }
            if($type[$xml['application']['type']]['dep'] == 0){
                $movePath = $type[$xml['application']['type']]['path'];
            }else{
                $movePath = $type[$xml['application']['type']]['path'] . "{$identifie}/";
            }
        }

        //todo:备份原文件

        Directory::copy($unzipRuntimePath,$movePath);
        File::delFile($zipFile); //删除zip包
        Directory::del($unzipRuntimePath); //删除解压目录数据
        $this->package->deleteCache(); //清楚缓存执行安装请求
        $this->run($identifie,$version,$action); //执行安装请求
    }

    // zip包解压
    private function unzip($filePath,$fileTo)
    {
        $zip = new \ZipArchive();

        if ($zip->open($filePath) === true) {
            $zip->extractTo($fileTo);
            $zip->close();
            return true;
        } else {
            return false;
        }
    }
}