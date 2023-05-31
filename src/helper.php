<?php
/**
 * user: peter
 * datetime：2023-05-24 15:43:29
 */

use think\facade\Config;
use think\facade\Log;

if(!function_exists('read_file')){
    /**
     * 读取文件内容
     * @param $filename  文件名
     * @return string 文件内容
     */
    function read_file($filename)
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
}

if(!function_exists('read_xml')){
    /**
     * 读取xml
     * @param $str
     * @return false|mixed
     */
    function read_xml($filename)
    {
        $content = read_file($filename);
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
}

if(!function_exists('write_package_install_log')){
    /**
     * 写安装日志
     * @param $logMessage
     * @return void
     */
    function write_package_install_log($logMessage)
    {
        // 添加日期时间前缀
        $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $logMessage . PHP_EOL;

        // 追加日志内容到文件
        file_put_contents(Config::get('package.runtime') . 'install.log', $logMessage, FILE_APPEND);
    }
}

if(!function_exists('read_package_install_log')){
    /**
     * 读安装日志
     * @param $linesCount
     * @return array
     * @throws Exception
     */
    function read_package_install_log($linesCount = 50) {
        $lines = [];

        $fileHandle = fopen(Config::get('package.runtime') . 'install.log', 'r');
        if ($fileHandle === false) {
            throw new Exception('Failed to open file.');
        }

        fseek($fileHandle, 0, SEEK_END);
        $position = ftell($fileHandle) - 1;
        $lineCount = 0;

        while ($position >= 0 && $lineCount < $linesCount) {
            fseek($fileHandle, $position);
            $char = fgetc($fileHandle);

            if ($char === "\n") {
                $line = fgets($fileHandle);
                array_unshift($lines, rtrim($line, "\r\n"));
                $lineCount++;
            }

            $position--;
        }

        // 处理第一行
        fseek($fileHandle, 0);
        $line = fgets($fileHandle);
        array_unshift($lines, rtrim($line, "\r\n"));

        fclose($fileHandle);

        // 去除最后一行的空行和空白字符
        if (count($lines) > 0) {
            $lastLine = end($lines);
            $trimmedLastLine = rtrim($lastLine);
            if ($trimmedLastLine === '') {
                array_pop($lines);
            } else {
                $lines[count($lines) - 1] = $trimmedLastLine;
            }
        }

        return $lines;
    }
}

if(!function_exists('executeOnceWithFileLock')){
    /**
     * 文件锁执行，防多进程并发，单进程相同锁不能多次使用
     * @param $callback
     * @param $fileName
     * @return bool
     * @throws Exception
     */
    function executeOnceWithFileLock($callback, $fileName) {
        $lockFilePath = $fileName . '.lock';

        $fileHandle = fopen($lockFilePath, 'c+');
        if ($fileHandle === false) {
            throw new Exception('Failed to open lock file.');
        }

        if (flock($fileHandle, LOCK_EX | LOCK_NB)) {
            try {
                call_user_func($callback);
                return true;
            } finally {
                flock($fileHandle, LOCK_UN);
                fclose($fileHandle);
                unlink($lockFilePath); // 删除锁文件
            }
        } else {
            fclose($fileHandle);
        }

        return false;
    }
}

if(!function_exists('removeDir')){
    function removeDir($dir){
        if ( ! is_dir( $dir ) ) {
            return true;
        }
        $dir = rtrim($dir,'/');
        $handle = @opendir($dir);
        while (($file = @readdir($handle)) !== false){
            if($file != '.' && $file != '..'){
                $dirFile = $dir . '/' . $file;
                is_dir( $dirFile ) ? removeDir( $dirFile ) : @unlink( $dirFile );
            }
        }
        closedir($handle);
        return rmdir( $dir );
    }
}
