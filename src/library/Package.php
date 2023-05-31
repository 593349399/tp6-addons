<?php
/**
 * user: peter
 * datetime：2023-05-24 15:43:29
 */
namespace Gdpeter\Tp6Addons\library;

use Gdpeter\Tp6Addons\library\DownloadTool;
use Gdpeter\Tp6Addons\PackageException;
use Gdpeter\Tp6Addons\Service;
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
class Package
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var Service
     */
    private $package;

    private $burst;
    private $sqlBurst;
    private $sqlFromPre;
    private $sqlToPre;
    private $runtimePath;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->package = $this->app->get('package');

        $this->burst = Config::get('package.burst');
        $this->sqlBurst = Config::get('package.sql_burst');
        $this->sqlFromPre = Config::get('package.sql_from_pre');
        $this->sqlToPre = Config::get('database.connections.mysql.prefix');
        $this->runtimePath =  Config::get('package.runtime');
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
        $logData = compact('identifie','version','action');

        $package = $this->package->getPackage($identifie);

        if($package['version'] != $version){
            $logData['package'] = $package;
            throw new PackageException('更新版本和本地版本不一致，清除缓存后重试',$logData);
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
                write_package_install_log("SQL执行第{$key}条：{$value}");
                while ($errorNum--){
                    try {
                        \think\facade\Db::execute($value);
                        continue 2; //成功跳出当前循环
                    } catch (\Throwable $e) {
                        if(!$errorNum){
                            write_package_install_log("SQL执行第{$key}条错误：{$e->getMessage()}；SQL：{$value}");
                        }
                    }
                }
            }
        }
    }

    /**
     * 一键安装
     * @param $identifie
     * @param $version
     * @param $url
     * @param $md5
     * @return bool 成功/失败
     * @throws PackageException
     */
    public function installPackage($identifie,$version,$url,$md5)
    {
        //防并发文件锁，保证多进程只有一个可以进入更新环节，
        return executeOnceWithFileLock(function () use ($identifie,$version,$url,$md5){
            $package = $this->package->getPackage($identifie);
            if($package['version'] == $version){
                throw new PackageException(1001); //该版本已升级成功
            }
            write_package_install_log("准备升级版本：{$version}");

            $zipRuntimePath = $this->runtimePath . 'zip/';//zip下载目录
            $unzipRuntimePath = $this->runtimePath . "unzip/{$identifie}_{$version}/"; //解压根目录
            Directory::create($zipRuntimePath);
            Directory::create($unzipRuntimePath);
            $zipFile = $zipRuntimePath . $identifie . '_' . $version . '.zip'; //zip文件

            //执行下载
            write_package_install_log("正在下载安装包：{$url}");
            try {
                app(DownloadTool::class)->setUrl($url)->setBurst($this->burst)->saveFile($zipFile);
            }catch (\Throwable $e){
                write_package_install_log("下载安装包错误：{$e->getMessage()}");
                File::delFile($zipFile); //删除zip包
                throw $e;
            }

            write_package_install_log("正在进行安装包MD5验证...");
            $md5File = md5_file($zipFile);
            if($md5File != $md5){
                write_package_install_log("安装包MD5验证失败：{$md5File}；{$md5}");
                File::delFile($zipFile); //删除zip包
                throw new PackageException(1002); //安装包验证错误，需要重新下载
            }

            write_package_install_log("正在解压安装包...");
            try {
                //执行解压
                if(!$this->unzip($zipFile,$unzipRuntimePath)){
                    throw new PackageException("解压失败请重试！");
                }
                //读取安装包的配置
                $xml = $this->package->readXml($unzipRuntimePath . 'package.xml');
                if($xml['identifie'] != $identifie){
                    throw new PackageException("安装包identifie错误，请联系开发者！");
                }
                if($xml['version'] != $version){
                    throw new PackageException("安装包version错误，请联系开发者！");
                }
            }catch (\Throwable $e){
                write_package_install_log("安装包解压失败：{$e->getMessage()}");
                File::delFile($zipFile); //删除zip包
                removeDir($unzipRuntimePath); //删除解压目录数据
                throw $e;
            }

            //执行更新
            if($package){
                $action = 'upgrade';
                $movePath = $package['rootPath'];
            }else{
                $action = 'install';
                $type = Config::get('package.type');
                if($type[$xml['type']]['dep'] == 0){
                    $movePath = $type[$xml['type']]['path'];
                }else{
                    $movePath = $type[$xml['type']]['path'] . "{$identifie}/";
                }
            }

            write_package_install_log('正在安装...');
            Directory::copy($unzipRuntimePath,$movePath); //复制文件
            File::delFile($zipFile); //删除zip包
            removeDir($unzipRuntimePath); //删除解压目录数据
            $this->package->deleteCache(); //清楚缓存执行安装请求
            try {
                $this->run($identifie,$version,$action); //执行安装请求
            }catch (\Throwable $e){
                write_package_install_log("安装错误警告：{$e->getMessage()}");
            }

            write_package_install_log("{$version}安装成功！");
        },$this->runtimePath . 'install');
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

    public function checkIsInstall()
    {
        return file_exists(Config::get('package.runtime') . 'install');
    }
}