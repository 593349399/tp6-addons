<?php
/**
 * user: peter
 * datetime：2023-05-24 15:43:29
 */

namespace Gdpeter\Tp6Addons\library;

use Gdpeter\Tp6Addons\PackageException;

class DownloadTool
{
    /**
     * 远程文件的路径
     * @var string
     */

    private $siteUrl = '';

    /**
     * 分片下载大小
     * @var int
     */

    private $burstBytes = 2048;

    /**
     * 设置远程下载文件的路径
     * @param string $url 远程文件的路径
     * @return $this
     */

    public function setUrl($url)
    {
        $this->siteUrl = $url;
        return $this;

    }

    /**
     * 设置分段下载字节大小
     * @param int $byte 字节大小
     * @return $this
     */
    public function setBurst($byte)
    {
        $this->burstBytes = $byte;
        return $this;
    }

    /**
     * 获取远程文件的信息
     * @return array
     * @throws Exception
     */

    private function getSiteFiLeInfo()
    {
        if (!$this->siteUrl) {
            throw new PackageException('请先设置远程文件url!');
        }

        $responseHeader = get_headers($this->siteUrl, 1);

        if (!$responseHeader) {
            throw new PackageException('获取远程文件信息失败!',$responseHeader);

        }

        if (!empty($responseHeader['Location'])) {
            //处理文件下载302问题
            $this->siteUrl = $responseHeader['Location'];

            return $this->getSiteFiLeInfo();

        }

        if(!empty($responseHeader[0]) && false == strpos($responseHeader[0],'200 OK')){
            throw new PackageException('远程文件错误：'.$responseHeader[0]);
        }

        return $responseHeader;

    }

    /**
     * 保存文件到本地
     * @param string $fileName 保存到本地的文件名称
     * @throws
     */

    public function saveFile($fileName)
    {
//获取远程文件的信息
        $siteFileInfo = $this->getSiteFiLeInfo();

        $siteFileLength = $siteFileInfo['Content-Length'] ?? 0;

//根据文件是否存在创建文件句柄、计算断点下载开始字节
        $fd = null;

        if (file_exists($fileName)) {
            $fd = fopen($fileName, 'ab');

        } else {
            $fd = fopen($fileName, 'wb');

        }

        if (!$fd) {
            throw new PackageException('创建或打开本地文件失败！');
        }

        //加上文件锁,防止刷新抢占资源句柄
        if (!flock($fd, LOCK_EX | LOCK_NB)) {
            throw new PackageException('已有相关进程操作执行下载本文件!');
        }

        //检查文件是否已经下载完成
        $fileSize = filesize($fileName);
        if ($fileSize && $fileSize >= $siteFileLength) {
            return; //下载完成
        }

        //计算断点下载结束字节
        $sByte = $fileSize;
        $eByte = $sByte + $this->burstBytes;

        //循环下载文件
        while (true) {
        //文件下载完成

            if ($fileSize >= $siteFileLength) {
                fclose($fd);

                break;
            }

//传递分片范围

            $xRange = "{$sByte}-$eByte";
//请求curl
            $result = $this->curl($xRange);
//检查是否正常请求
            $code = $result['code'] ?? 0;

            if (!$code) {
                throw new PackageException('Http请求异常!');
            }

            if ($code != 206) {
                throw new PackageException('Http状态码异常,可能不支持断点的资源或已完成下载!');
            }
//返回流长度
            $streamLength = $result['length'] ?? 0;
//返回流内容

            $streamContent = $result['stream'] ?? '';

            if ($streamLength > 0) {

                $saveRes = fwrite($fd, $streamContent);

                if (!$saveRes) {
                    throw new PackageException('写入流到文件失败!');
                }

                if ($saveRes != $streamLength) {
//讲道理这种情况基本不会遇到,除非分段数设置过大,暂时未做兼容处理,重新执行就行
                    throw new PackageException('数据异常:返回大小和写入大小不一致!');
                }

//递增range
                $sByte = $eByte + 1;
                $eByte = $sByte + $this->burstBytes;
//记录文件大小
                $fileSize = $fileSize + $saveRes;
            }

        }

    }

    /**
     * 获取下载文件流
     * @param string $range 分片字节范围
     * @param array $header Http请求头
     * @return array
     * @throws
     */

    private function curl($range, $header = [])

    {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->siteUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        curl_setopt($ch, CURLOPT_HEADER, TRUE);

//设置关闭SSL

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

//设置分片

        curl_setopt($ch, CURLOPT_RANGE, $range);

//设置header

        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        }

//执行请求

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new PackageException('下载文件异常:' . curl_error($ch));

        }

//提取response_header和response_body

        $headSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $httpHeader = substr($response, 0, $headSize);

        if (!$httpHeader) {
            throw new PackageException('下载文件异常:未获取到响应头');

        }

        $fileStream = substr($response, $headSize);

//解析header

        $length = $this->getResHeaderValue('Content-Length', $httpHeader);

        $httpCode = $this->getResHeaderValue('Http-Code', $httpHeader);

        curl_close($ch);

//返回

        return [

            'code' => $httpCode,

            'length' => $length,

            'stream' => $fileStream,

        ];

    }

    /**
     * 获取响应头某个Key的值
     * @param string $key header头的key
     * @param string $responseHead header头字符串
     * @return string
     */

    private function getResHeaderValue($key, $responseHead)

    {
        $value = '';

        $headArr = explode("\r\n", $responseHead);

        foreach ($headArr as $loop) {
            if ($key == 'Http-Code') {
                if (preg_match('/HTTP\/1\.[0-9]{1} ([0-9]{3})/', $loop, $matches)) {
                    return $matches['1'];

                }

            } else {
                if (strpos($loop, $key) !== false) {
                    $value = trim(str_replace($key . ':', '', $loop));

                }

            }

        }

        return $value;

    }

}