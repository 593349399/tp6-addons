<?php
/**
 * user: peter
 * Date：2021/8/10
 * Time: 11:57
 */

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

if(!function_exists('get_cache_name')){
    /**
     * 读取经过拼接的统一的缓存前缀名称
     * @param $name
     */
    function get_cache_name($name)
    {
        return \think\facade\Config::get('package.cache_pre') . $name;
    }
}

if(!function_exists('is_serialized')){
    /**
     * 是否序列化
     * @param $str
     * @return bool
     */
    function is_serialized($str){
        if (!is_string($str)) {
            return false;
        }
        $str = trim($str);

        if ('N;' == $str)

            return true;

        if (!preg_match('/^([adObis]):/', $str, $badions))

            return false;

        switch ($badions[1]) {

            case'a':

            case'O':

            case's':

                if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $str))

                    return true;

                break;

            case'b':

            case'i':

            case'd':

                if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $str))

                    return true;

                break;

        }

        return false;
    }
}

/**
 * todo:文件锁
 * @param Closure $func
 * @param string $fileName
 * @param int $ttl
 * @param int $retryTime
 * @return mixed
 */
function fileLock(Closure $func, string $fileName, int $ttl = 3, int $retryTime = 3){
//    if($fp=fopen($fileName,'w')){
//        $startTime=microtime(); //开始时间
//        do{
//            $canDo=flock($fp,LOCK_EX);
//            if($canDo){
//                sleep(10);
//            }else{
//                echo $canDo;exit;
//            }
//            if(!$canDo){
//                usleep(round(rand(0,100)*1000));
//            }
//        }while((!$canDo)&&((microtime()-$startTime)<1000));
//
//        if($canDo){
//            try {
//                $res = $func();
//            }catch (Throwable $e) {
//                fclose($fp);
//                throw $e;
//            }
//        }
//
//        fclose($fp);
//        return $res;
//    }
}